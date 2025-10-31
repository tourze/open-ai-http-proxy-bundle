<think>**分析用户需求**

- 用户希望创建一个名为 open-ai-http-proxy-bundle 的 Symfony bundle。
- 这个 bundle 需要维护 OpenAI 或兼容的 API keys。
  </think>

# OpenAI HTTP Proxy Bundle PRD

## 1. 文档信息
- **文档标题**：OpenAI HTTP Proxy Bundle 产品需求文档 (PRD)
- **版本**：1.0
- **作者**：Chatbot
- **日期**：2025-08-08
- **状态**：草稿
- **描述**：本文档定义了 Symfony Bundle "open-ai-http-proxy-bundle" 的功能需求、非功能需求、用例和实现指南。该 Bundle 旨在为 OpenAI 或兼容 API 提供一个代理服务，实现键管理、负载均衡和模型动态切换。

## 2. 产品概述
### 2.1 产品背景
随着 AI 模型如 OpenAI 的广泛应用，开发者常常需要管理多个 API 键（keys）来避免单键限额问题。同时，需要一个统一的 HTTP 端点来代理请求，以实现键的自动轮询使用。此外，在某些场景下，需要动态切换模型（如从 GPT-3.5 切换到 GPT-4），以优化成本或性能，而不影响客户端调用。

### 2.2 产品目标
- 提供一个易于集成的 Symfony Bundle，用于管理 OpenAI 或兼容 API 的键。
- 通过统一的 HTTP 端点代理所有请求，内部实现键的轮询（round-robin）或负载均衡。
- 支持高级特性，如模型动态替换，提高灵活性和资源利用率。
- 确保 Bundle 兼容 Symfony 6.x+，并易于扩展。

### 2.3 目标用户
- Symfony/PHP 开发者：构建 AI 应用的 backend 工程师。
- AI 服务提供者：需要代理多个键的企业或个人开发者。
- 成本优化者：希望通过模型切换降低费用的用户。

### 2.4 产品范围
- **In Scope**：
    - API 键管理（添加、删除、验证）。
    - HTTP 代理端点，支持 OpenAI API 的标准请求（如 chat completions、embeddings 等）。
    - 键轮询机制。
    - 模型动态切换规则。
- **Out of Scope**：
    - 实时监控仪表盘（可作为未来扩展）。
    - 非 OpenAI 兼容 API 的支持（如自定义 AI 服务）。
    - 安全性审计（如加密存储键，需用户自行配置）。

## 3. 功能需求
### 3.1 核心功能
1. **API 键管理**
    - 支持维护多个 OpenAI 或兼容 API 的键（例如 Azure OpenAI、自定义兼容端点）。
    - 配置方式：通过 YAML 配置或数据库存储（可选，使用 Doctrine）。
    - 功能点：
        - 添加键：包括键值、端点 URL（默认 https://api.openai.com）、权重（用于负载均衡）。
        - 删除/禁用键：标记键为无效，避免使用。
        - 验证键：Bundle 初始化时或手动触发，检查键的有效性（通过发送测试请求）。
        - 键状态：可用、限额已达、失效。

2. **统一 HTTP 端点**
    - 提供一个通用路由，例如 `/openai-proxy/{path}`，其中 `{path}` 是 OpenAI API 的子路径（如 v1/chat/completions）。
    - 支持 HTTP 方法：POST、GET 等，兼容 OpenAI API 规范。
    - 请求处理：
        - 接收客户端请求（JSON body、headers）。
        - 内部转发到选定的键对应的端点。
        - 返回响应：直接透传 OpenAI 的响应，或在错误时提供自定义错误消息。

3. **键轮询机制**
    - 自动轮询：使用 round-robin 算法，选择下一个可用键。
    - 支持权重：基于键的权重分配请求（例如，高权重键更频繁使用）。
    - 错误处理：如果键失效（rate limit 或错误），跳过并重试下一个键，最多重试 N 次（配置项）。
    - 负载均衡策略：默认 round-robin，可扩展为 least-connections 或随机。

### 3.2 高级功能
1. **模型动态切换**
    - 支持规则-based 模型替换：例如，如果请求模型为 "gpt-3.5-turbo"，可配置替换为 "gpt-4" 或其他兼容模型。
    - 配置方式：YAML 中的映射规则，如：
      ```
      model_mappings:
        gpt-3.5-turbo: gpt-4
        text-davinci-003: gpt-3.5-turbo
      ```
    - "偷偷" 切换：客户端请求中指定的模型被替换，但响应中伪装为原模型（可选配置）。
    - 条件切换：基于键的限额、时间、用户角色等（未来扩展）。

2. **日志和监控**
    - 记录每个请求：使用的键、模型、响应状态、耗时。
    - 集成 Symfony Logger，支持输出到文件或外部服务（如 ELK）。
    - 指标暴露：可选集成 Prometheus，暴露 metrics 如请求数、错误率。

3. **配置选项**
    - Bundle 配置示例（config/packages/open_ai_http_proxy.yaml）：
      ```
      open_ai_http_proxy:
        keys:
          - key: sk-xxx1
            endpoint: https://api.openai.com
            weight: 1
          - key: sk-xxx2
            endpoint: https://custom-ai.com
            weight: 2
        model_mappings:
          gpt-3.5-turbo: gpt-4
        retry_count: 3
        log_level: debug
      ```

### 3.3 用例
1. **基本代理请求**
    - 用户发送 POST /openai-proxy/v1/chat/completions { "model": "gpt-3.5-turbo", "messages": [...] }
    - Bundle 选择一个键，转发请求，返回响应。

2. **键轮询**
    - 连续发送 3 个请求：分别使用 key1、key2、key1（round-robin）。

3. **模型切换**
    - 请求模型 "gpt-3.5-turbo"，内部替换为 "gpt-4"，响应中 model 字段仍为 "gpt-3.5-turbo"。

4. **错误处理**
    - 如果所有键失效，返回 429 Too Many Requests 或自定义错误。

## 4. 非功能需求
### 4.1 性能
- 延迟：代理增加 < 50ms（不包括 OpenAI 响应时间）。
- 吞吐量：支持每秒 100+ 请求（取决于服务器配置）。
- 并发：使用 Symfony 的异步处理（如 Messenger）支持高并发。

### 4.2 安全性
- 键存储：不硬编码，推荐使用环境变量或 Secrets Manager。
- 认证：端点可选添加 API Key 认证（配置项）。
- 输入验证：防止注入攻击，验证 JSON schema 符合 OpenAI 规范。

### 4.3 可用性
- 兼容性：Symfony 6.x+，PHP 8.1+。
- 国际化：支持多语言错误消息（默认英文）。
- 文档：提供 README.md，包括安装、配置示例、API 文档。

### 4.4 可靠性
- 容错：键失效时自动 failover。
- 测试覆盖：单元测试（PHPUnit）覆盖 80%+ 代码。

### 4.5 可扩展性
- 插件化：允许自定义轮询策略或模型切换逻辑（通过 Event Dispatcher）。
- 版本控制：遵循 Semantic Versioning。

## 5. 系统架构
- **组件**：
    - Configuration：处理 YAML 配置。
    - KeyManager：管理键列表、验证、选择。
    - ProxyController：处理 HTTP 请求，转发到 HttpClient。
    - ModelSwitcher：应用模型替换规则。
    - Logger：记录事件。
- **依赖**：
    - Symfony HttpClient：用于转发请求。
    - Doctrine（可选）：持久化键。
- **流程图**（文本描述）：
    1. 接收请求 → 验证 → 选择键 → 应用模型切换 → 转发请求 → 处理响应 → 返回。

## 6. 安装和配置
- **安装**：composer require your-vendor/open-ai-http-proxy-bundle
- **启用**：在 bundles.php 中添加。
- **路由**：自动加载 /openai-proxy/*。
- **测试**：提供示例命令 symfony console openai:proxy:test-key。

## 7. 风险和假设
- **风险**：OpenAI API 变更可能导致兼容性问题（需定期更新）。
- **假设**：用户熟悉 Symfony；键兼容 OpenAI API 格式。
- **依赖变更**：如果 OpenAI 更新模型列表，需手动更新映射。

## 8. 附录
- **API 文档示例**：
    - Endpoint: POST /openai-proxy/v1/chat/completions
    - Body: 同 OpenAI API
    - Headers: Content-Type: application/json
- **未来 roadmap**：
    - 支持更多 AI 提供者（如 Anthropic）。
    - UI 管理界面（Admin Bundle 集成）。
