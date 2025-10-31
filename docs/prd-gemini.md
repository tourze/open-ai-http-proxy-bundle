# OpenAI HTTP Proxy Bundle 产品需求文档 (PRD)

## 1. 概述

### 1.1. 项目目标

`OpenAI HTTP Proxy Bundle` 是一个为 Symfony 应用程序设计的扩展包，旨在提供一个统一、可靠且高度可配置的 OpenAI API 代理服务。它解决了直接与 OpenAI API 或其兼容服务（如 Azure OpenAI, Google AI Studio, Anthropic Claude 等）交互时遇到的常见挑战，例如 API 密钥的有效管理、负载均衡、故障转移和成本控制。

通过提供一个中央代理端点，该 Bundle 允许应用程序将所有 AI 模型请求路由到一个地方，而无需在业务逻辑中处理复杂的密钥轮换、模型映射和错误重试逻辑。这极大地简化了 AI 功能的集成，提高了系统的健壮性和可扩展性。

### 1.2. 核心功能

*   **统一的 API 端点**：提供一个 `/api/v1/chat/completions` 代理端点，兼容 OpenAI 的请求和响应格式。
*   **多 Key 管理与轮询**：支持配置多个 OpenAI 或兼容服务的 API 密钥，并按预设策略（如轮询、随机）进行调用，实现负载均衡和故障转移。
*   **动态模型映射**：允许将请求中的模型名称（如 `gpt-4`）动态映射到实际使用的不同模型（如 `azure-gpt-4-turbo` 或 `claude-3-opus`），甚至可以根据 Key 的不同使用不同的映射关系。
*   **详细的日志和统计**：记录每个请求的详细信息，包括使用的 Key、模型、响应时间、Token 消耗等，为成本分析和性能监控提供数据支持。
*   **灵活的配置**：通过 Symfony 的配置文件，可以轻松管理 API 密钥、模型映射、路由策略等。

### 1.3. 目标用户

*   **Symfony 开发者**：需要在其应用程序中集成 OpenAI 或类似 AI 服务的开发者。
*   **系统架构师**：寻求构建可扩展、高可用的 AI 驱动功能的技术负责人。
*   **运维团队**：需要监控和管理 AI 服务调用成本和稳定性的团队。

## 2. 功能详述

### 2.1. 统一 API 代理端点

#### 2.1.1. 端点定义

*   **URL**: `/api/v1/chat/completions`
*   **Method**: `POST`
*   **Request Body**: 与 OpenAI `v1/chat/completions` API 完全兼容的 JSON 对象。
    *   示例:
        ```json
        {
          "model": "gpt-4",
          "messages": [
            {
              "role": "user",
              "content": "Hello!"
            }
          ],
          "stream": false
        }
        ```
*   **Response Body**:
    *   **非流式 (stream: false)**: 返回与 OpenAI `v1/chat/completions` API 兼容的 JSON 响应。
    *   **流式 (stream: true)**: 返回与 OpenAI `v1/chat/completions` API 兼容的 Server-Sent Events (SSE) 流。

#### 2.1.2. 认证方式

代理端点本身应受保护。初始版本可以使用简单的 Bearer Token 认证，通过 Symfony 的安全机制进行配置。请求头中需包含 `Authorization: Bearer <PROXY_ACCESS_TOKEN>`。

### 2.2. API 密钥管理 (Key Pool)

#### 2.2.1. 配置格式

API 密钥池通过 `config/packages/open_ai_http_proxy.yaml` 进行配置。每个 Key 都应包含以下属性：

*   `key`: API 密钥字符串。
*   `platform`: 服务平台，如 `openai`, `azure`, `anthropic`, `google` 等 (可枚举)。
*   `base_uri`: 该 Key 对应的 API 服务地址。
*   `priority`: 优先级（整数，数字越小优先级越高），用于实现基于优先级的故障转移。
*   `weight`: 权重（整数），用于加权轮询或随机策略。
*   `limit`: 可选，该 Key 的速率限制（如每分钟请求次数）。
*   `enabled`: `true` 或 `false`，用于动态启用或禁用某个 Key。
*   `tags`: 可选，用于对 Key 进行分组的标签数组，如 `['fast', 'cheap']`。

**示例配置:**

```yaml
# config/packages/open_ai_http_proxy.yaml
open_ai_http_proxy:
    keys:
        -   key: 'sk-openai-xxxxxxxx'
            platform: 'openai'
            base_uri: 'https://api.openai.com/v1/'
            priority: 1
            weight: 10
            tags: ['default', 'gpt-4']
        -   key: 'azure-key-yyyyyyyy'
            platform: 'azure'
            base_uri: 'https://my-azure-deployment.openai.azure.com/'
            priority: 2
            weight: 5
            tags: ['azure', 'stable']
        -   key: 'claude-key-zzzzzzzz'
            platform: 'anthropic'
            base_uri: 'https://api.anthropic.com/v1/'
            priority: 3
            weight: 8
            tags: ['claude-opus']
```

#### 2.2.2. 轮询与选择策略

当收到请求时，Bundle 需要从 Key Pool 中选择一个合适的 Key。支持以下策略：

1.  **轮询 (Round Robin)**: 按顺序循环使用所有可用的 Key。
2.  **加权轮询 (Weighted Round Robin)**: 根据配置的 `weight` 值分配请求。
3.  **随机 (Random)**: 随机选择一个可用的 Key。
4.  **加权随机 (Weighted Random)**: 根据 `weight` 值进行加权随机选择。
5.  **基于优先级 (Priority-Based)**: 始终选择可用的最高优先级 (`priority` 值最小) 的 Key。只有当高优先级的 Key 全部失败时，才会使用次一级优先级的 Key。

默认策略应为 `轮询`。策略可在配置文件中全局设置。

### 2.3. 动态模型映射

这是本 Bundle 的核心高级功能。它允许将客户端请求的通用模型名称映射到特定 Key 所支持的具体模型。

#### 2.3.1. 配置格式

模型映射在 `open_ai_http_proxy.yaml` 中配置。可以设置全局映射和针对特定 Key 的覆盖映射。

**示例配置:**

```yaml
# config/packages/open_ai_http_proxy.yaml
open_ai_http_proxy:
    model_mapping:
        # 全局映射
        'gpt-4-turbo': 'openai-gpt-4-turbo-2024-04-09'
        'gpt-3.5-turbo': 'openai-gpt-3.5-turbo-0125'
        'claude-3-opus': 'anthropic-claude-3-opus-20240229'

    keys:
        -   key: 'sk-openai-xxxxxxxx'
            # ...
            model_mapping: # 覆盖全局映射
                'gpt-4-turbo': 'special-internal-gpt-4-model'

        -   key: 'azure-key-yyyyyyyy'
            platform: 'azure'
            # ...
            model_mapping:
                # Azure 通常需要 deployment_id 作为模型名称
                'gpt-4-turbo': 'my-azure-gpt4-deployment'
```

#### 2.3.2. 工作流程

1.  客户端发起请求，`model` 字段为 `gpt-4-turbo`。
2.  Bundle 根据 Key 选择策略，选中了 `azure-key-yyyyyyyy`。
3.  Bundle 检查该 Key 是否有针对 `gpt-4-turbo` 的特定 `model_mapping`。
4.  发现映射关系：`'gpt-4-turbo': 'my-azure-gpt4-deployment'`。
5.  Bundle 修改请求体，将 `model` 字段的值替换为 `my-azure-gpt4-deployment`。
6.  将修改后的请求发送到该 Key 的 `base_uri`。

如果某个 Key 没有特定的映射，则使用全局映射。如果全局映射也不存在，则直接使用客户端请求的原始模型名称。

### 2.4. 日志与统计

#### 2.4.1. 日志记录

对于每次通过代理的 API 调用，都应使用 Symfony 的 `monolog` 组件记录一条日志。日志级别应为 `INFO` (成功) 或 `ERROR` (失败)。

**日志内容应包括:**

*   `request_id`: 唯一请求 ID。
*   `selected_key_fingerprint`: 所选 Key 的指纹（例如，`sk-...-abcd`），避免完整记录 Key。
*   `platform`: `openai`, `azure`, etc.
*   `request_model`: 客户端请求的原始模型。
*   `mapped_model`: 实际发送到目标服务的模型。
*   `is_stream`: 是否为流式请求。
*   `status_code`: 从目标服务收到的 HTTP 状态码。
*   `response_time_ms`: 响应耗时（毫秒）。
*   `usage`: (如果成功且非流式) 从响应中解析出的 `usage` 对象 (prompt_tokens, completion_tokens, total_tokens)。

#### 2.4.2. 统计与监控

Bundle 应提供一个 Symfony Command (`bin/console openai:proxy:stats`) 来展示基本的统计信息，例如：

*   每个 Key 的总请求数、成功率。
*   每个模型的总请求数。
*   总的 Token 使用量。

未来可以考虑与 Prometheus 或其他监控系统集成。

### 2.5. 错误处理与故障转移

*   **自动重试**: 当请求失败时（例如，网络错误、5xx 服务器错误），Bundle 应自动尝试使用下一个可用的 Key 进行重试。最大重试次数应可配置。
*   **速率限制处理**: 当收到 `429 Too Many Requests` 错误时，应将该 Key 标记为在一段时间内（例如，根据 `Retry-After` 响应头）不可用，并立即尝试下一个 Key。
*   **无效 Key 处理**: 当收到 `401 Unauthorized` 或 `403 Forbidden` 错误时，应将该 Key 标记为永久不可用（或在很长一段时间内），并记录严重错误日志。

## 3. 技术实现与架构

### 3.1. 核心服务

*   `KeyManager`: 负责从配置中加载和管理所有 API Key。
*   `KeySelector`: 根据配置的策略（轮询、随机等）从 `KeyManager` 中选择一个 Key。
*   `ModelMapper`: 处理模型名称的动态映射。
*   `ProxyController`: 处理进入的 HTTP 请求，协调其他服务完成代理逻辑。
*   `UpstreamClient`: 使用 `Symfony/HttpClient` 向最终的 AI 服务（OpenAI, Azure 等）发起请求。

### 3.2. 依赖

*   `symfony/framework-bundle`
*   `symfony/http-client`
*   `symfony/config`
*   `symfony/dependency-injection`
*   `monolog/monolog`

### 3.3. 配置文件

`config/routes/open_ai_http_proxy.yaml`:
```yaml
open_ai_http_proxy_chat_completions:
    path: /api/v1/chat/completions
    controller: OpenAIHttpProxy\Controller\ProxyController::chatCompletions
    methods: [POST]
```

`config/services.yaml`:
配置核心服务的依赖注入。

`config/packages/open_ai_http_proxy.yaml`:
主配置文件，用于定义 Key 池、模型映射和策略。

## 4. 里程碑与未来规划

### 4.1. V1.0 (MVP)

*   实现统一的 `/api/v1/chat/completions` 端点。
*   支持非流式和流式响应。
*   实现基于配置的 Key 池管理。
*   实现轮询（Round Robin）和基于优先级的 Key 选择策略。
*   实现基本的动态模型映射（全局和 per-key）。
*   实现详细的日志记录。
*   提供基本的错误处理和故障转移（重试）。

### 4.2. V1.1

*   增加更多 Key 选择策略（加权、随机）。
*   实现对 `429 Too Many Requests` 的智能处理。
*   提供 `bin/console openai:proxy:stats` 统计命令。
*   增加对 Key 的 `tags` 支持，允许客户端请求时指定 tag (`X-Proxy-Key-Tags: cheap,fast`)。

### 4.3. V2.0 (未来展望)

*   **UI 管理界面**: 提供一个简单的 Web UI 来管理 Key 池和查看统计信息。
*   **数据库支持**: 将 Key 和日志存储在数据库中，而不是仅依赖配置文件。
*   **高级路由规则**: 支持基于请求内容（如 prompt 长度）来选择 Key 或模型。
*   **缓存**: 对完全相同的非随机请求提供缓存选项，以降低成本。
*   **多端点支持**: 代理其他 OpenAI API 端点，如 `/v1/embeddings`。
*   **插件系统**: 允许开发者添加自定义的 Key 选择策略或日志处理器。
