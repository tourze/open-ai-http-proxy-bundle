# OpenAI HTTP Proxy Bundle 产品需求文档（最终版）

## 1. 项目概述

### 1.1 背景与动机

随着大语言模型（LLM）的广泛应用，企业面临多重挑战：
- **多供应商管理**：需要同时对接 OpenAI、Azure OpenAI、Anthropic Claude、Google Gemini 等多个服务
- **配额限制**：单个 API Key 的速率和配额限制无法满足高并发需求
- **成本控制**：不同模型和供应商价格差异巨大，需要灵活切换
- **安全隐患**：直接暴露真实 API Key 给前端应用存在泄露风险
- **运维复杂**：缺乏统一的监控、日志和故障处理机制

### 1.2 解决方案

OpenAI HTTP Proxy Bundle 是一个企业级的 AI 服务代理解决方案，通过以下架构实现：

```
客户端应用 → [统一代理接口] → [智能调度层] → [多供应商适配] → AI服务
                    ↓                ↓                 ↓
              [内部认证]      [模型映射]       [健康监控]
```

### 1.3 核心价值

- **统一接口**：对外提供标准 OpenAI API 格式，屏蔽底层差异
- **高可用性**：多 Key 池管理、自动故障转移、智能重试
- **灵活调度**：支持轮询、加权、优先级等多种负载均衡策略
- **安全可控**：内部令牌体系、细粒度权限控制、密钥加密存储
- **运维友好**：EasyAdmin 后台、详细日志、实时监控

## 2. 功能架构

### 2.2 核心模块职责

#### 2.2.1 open-ai-bundle（账号管理模块）
- **API Key 管理**：多供应商密钥的 CRUD 操作
- **健康检查**：定期验证 Key 可用性和配额
- **使用统计**：记录每个 Key 的调用次数、成功率、Token 消耗

#### 2.2.2 http-forward-bundle（转发引擎模块）
- **请求代理**：HTTP/HTTPS 请求转发
- **流式处理**：支持 SSE（Server-Sent Events）流式响应
- **协议适配**：不同供应商的请求/响应格式转换

#### 2.2.4 open-ai-http-proxy-bundle（聚合层模块）
- **统一端点**：提供兼容 OpenAI 的代理接口
- **调度策略**：实现各种负载均衡算法
- **模型映射**：动态模型名称转换
- **管理后台**：EasyAdmin 集成的配置界面

## 3. 详细功能设计

### 3.1 统一代理接口

#### 3.1.1 支持的端点

| 端点路径 | 方法 | 功能描述 |
|---------|------|---------|
| `/proxy/v1/chat/completions` | POST | 对话补全（ChatGPT） |
| `/proxy/v1/completions` | POST | 文本补全 |
| `/proxy/v1/embeddings` | POST | 向量嵌入 |
| `/proxy/v1/moderations` | POST | 内容审核 |
| `/proxy/v1/images/generations` | POST | 图像生成 |
| `/proxy/v1/audio/transcriptions` | POST | 音频转录 |
| `/proxy/v1/models` | GET | 模型列表 |

#### 3.1.2 请求格式

```json
{
  "model": "gpt-4",
  "messages": [
    {"role": "user", "content": "Hello!"}
  ],
  "stream": false,
  "temperature": 0.7
}
```

#### 3.1.3 认证方式

```http
Authorization: Bearer <内部令牌>
X-Proxy-Tags: fast,cheap  # 可选：指定Key标签
X-Proxy-Model-Override: gpt-3.5-turbo  # 可选：强制模型
```

### 3.2 API Key 池管理

#### 3.2.1 数据模型

```php
class ApiKey {
    private string $id;
    private string $key;              // 加密存储
    private string $provider;         // openai|azure|anthropic|google
    private string $baseUri;          // API端点地址
    private array $models;            // 支持的模型列表
    private int $priority;            // 优先级（1-100）
    private int $weight;              // 权重（用于加权轮询）
    private ?int $rateLimit;          // 速率限制（请求/分钟）
    private ?int $quotaLimit;         // 配额限制（Token/月）
    private bool $enabled;            // 启用状态
    private array $tags;              // 标签（fast|cheap|stable）
    private ?array $customHeaders;    // 自定义请求头
    private ?array $modelMapping;     // 模型映射规则
}
```

#### 3.2.2 配置示例

```yaml
open_ai_http_proxy:
  keys:
    - key: '${env(OPENAI_KEY_1)}'
      provider: openai
      base_uri: 'https://api.openai.com/v1/'
      priority: 1
      weight: 10
      models: ['gpt-4', 'gpt-3.5-turbo']
      tags: ['production', 'fast']
      
    - key: '${env(AZURE_KEY_1)}'
      provider: azure
      base_uri: 'https://mycompany.openai.azure.com/'
      priority: 2
      weight: 5
      models: ['gpt-35-turbo', 'gpt-4']
      tags: ['stable', 'cheap']
      custom_headers:
        api-version: '2024-02-15-preview'
      model_mapping:
        'gpt-3.5-turbo': 'gpt-35-turbo-deployment'
        'gpt-4': 'gpt-4-deployment'
```

### 3.3 调度策略

#### 3.3.1 支持的策略

| 策略名称 | 描述 | 适用场景 |
|---------|------|---------|
| **轮询（Round Robin）** | 按顺序循环使用 | 负载均匀分布 |
| **加权轮询（Weighted RR）** | 根据权重分配请求 | 按Key性能分配 |
| **随机（Random）** | 随机选择 | 简单负载分散 |
| **加权随机（Weighted Random）** | 根据权重随机 | 概率性负载分配 |
| **优先级（Priority）** | 优先使用高优先级Key | 主备模式 |
| **最少使用（Least Used）** | 选择使用次数最少的 | 均衡使用 |
| **最低错误率（Least Errors）** | 选择错误率最低的 | 稳定性优先 |
| **标签匹配（Tag Based）** | 根据请求标签选择 | 场景化路由 |

#### 3.3.2 策略配置

```yaml
open_ai_http_proxy:
  default_strategy: weighted_round_robin
  strategies:
    production:
      type: priority
      failover_threshold: 3  # 连续失败3次后切换
    development:
      type: random
    cost_optimize:
      type: tag_based
      prefer_tags: ['cheap']
```

### 3.4 模型映射系统

#### 3.4.1 映射规则

```yaml
open_ai_http_proxy:
  # 全局映射规则
  global_model_mapping:
    'gpt-4': 'gpt-4-0613'
    'gpt-3.5-turbo': 'gpt-3.5-turbo-0613'
    
  # 基于条件的映射
  conditional_mapping:
    - condition:
        time_range: '00:00-06:00'  # 夜间
        model: 'gpt-4'
      target: 'gpt-3.5-turbo'  # 降级以节省成本
      
    - condition:
        token_count: '>4000'  # 长文本
        model: 'gpt-3.5-turbo'
      target: 'gpt-3.5-turbo-16k'  # 使用长上下文模型
```

#### 3.4.2 映射策略

- **静态映射**：固定的模型名称替换
- **动态映射**：基于运行时条件（时间、负载、成本）
- **降级映射**：高级模型不可用时自动降级
- **升级映射**：特定场景自动升级到更强模型

### 3.5 内部令牌系统

#### 3.5.1 令牌模型

```php
class InternalToken {
    private string $token;
    private string $name;
    private ?string $description;
    private array $permissions;    // 允许的模型/端点
    private ?int $rateLimit;      // 速率限制
    private ?int $dailyQuota;     // 每日配额
    private ?DateTime $expiresAt; // 过期时间
    private bool $active;
    private array $metadata;      // 自定义元数据
}
```

#### 3.5.2 权限控制

```yaml
permissions:
  - token: 'app_frontend_001'
    models: ['gpt-3.5-turbo']
    endpoints: ['/chat/completions']
    rate_limit: 100  # 请求/分钟
    daily_quota: 10000  # Token/天
    
  - token: 'app_backend_001'
    models: ['gpt-4', 'gpt-3.5-turbo']
    endpoints: ['*']
    rate_limit: 1000
    daily_quota: 100000
```

### 3.6 监控与日志

#### 3.6.1 日志记录

```json
{
  "request_id": "req_abc123",
  "timestamp": "2024-01-20T10:30:00Z",
  "internal_token": "app_xxx",
  "selected_key": "azure_key_001",
  "provider": "azure",
  "requested_model": "gpt-4",
  "actual_model": "gpt-4-deployment",
  "endpoint": "/chat/completions",
  "status_code": 200,
  "response_time_ms": 1250,
  "usage": {
    "prompt_tokens": 150,
    "completion_tokens": 200,
    "total_tokens": 350
  },
  "cost_usd": 0.0105,
  "error": null
}
```

#### 3.6.2 监控指标

| 指标名称 | 描述 | 告警阈值 |
|---------|------|---------|
| `request_rate` | 请求速率 | >1000/min |
| `error_rate` | 错误率 | >5% |
| `response_time_p95` | 95分位响应时间 | >5000ms |
| `key_usage_rate` | Key使用率 | >80% |
| `token_consumption` | Token消耗速率 | >限额90% |
| `cost_per_hour` | 每小时成本 | >$100 |

### 3.7 错误处理

#### 3.7.1 重试机制

```yaml
retry:
  max_attempts: 3
  backoff_strategy: exponential  # 指数退避
  backoff_multiplier: 2
  max_backoff: 30s
  retry_on_status: [429, 500, 502, 503, 504]
  retry_on_error: ['timeout', 'connection_failed']
```

#### 3.7.2 故障转移

- **自动切换**：Key失败时自动尝试下一个
- **熔断机制**：连续失败N次后暂时禁用
- **降级策略**：模型不可用时自动降级
- **兜底响应**：全部失败时返回缓存或默认响应

### 3.8 EasyAdmin 后台

#### 3.8.1 管理界面

- **Dashboard**：实时监控面板
  - 请求统计图表
  - Key健康状态
  - 成本统计
  - 错误率趋势

- **Key管理**：
  - 添加/编辑/删除Key
  - 批量导入/导出
  - 健康检查
  - 使用统计

- **令牌管理**：
  - 生成/吊销令牌
  - 权限配置
  - 使用记录

- **模型映射**：
  - 映射规则配置
  - 测试映射
  - 批量更新

- **日志查询**：
  - 实时日志流
  - 高级筛选
  - 导出功能

## 4. 技术实现

### 4.1 技术栈

- **框架**：Symfony 6.4+
- **PHP版本**：8.1+
- **核心组件**：
  - Symfony HttpClient（请求转发）
  - Doctrine ORM（数据持久化）
  - EasyAdmin 4（管理界面）
  - Monolog（日志处理）
  - Symfony Messenger（异步处理）
  - Symfony Cache（缓存）

### 4.2 性能要求

- **延迟**：代理增加延迟 <50ms
- **吞吐量**：支持 1000+ QPS
- **并发**：支持 500+ 并发连接
- **可用性**：99.9% SLA

### 4.3 安全要求

- **密钥加密**：使用 Symfony Secrets 或 KMS
- **传输加密**：强制 HTTPS
- **访问控制**：基于角色的权限管理
- **审计日志**：记录所有管理操作
- **防护措施**：速率限制、IP白名单

## 5. 部署架构

### 5.1 推荐架构

```
[负载均衡器]
      ↓
[代理服务集群] ←→ [Redis缓存]
      ↓
[数据库主从]
```

### 5.2 扩展性设计

- **水平扩展**：无状态设计，支持多实例
- **缓存策略**：Redis缓存热点数据
- **异步处理**：Messenger队列处理日志
- **读写分离**：数据库主从分离

## 6. 实施计划

### 6.1 第一阶段（MVP）- 2周

- [x] 基础代理功能
- [x] 简单轮询策略
- [x] 基本日志记录
- [x] 配置文件管理

### 6.2 第二阶段（核心功能）- 3周

- [ ] 完整调度策略
- [ ] 模型映射系统
- [ ] 内部令牌认证
- [ ] EasyAdmin基础界面

### 6.3 第三阶段（高级功能）- 3周

- [ ] 条件映射规则
- [ ] 监控告警系统
- [ ] 成本分析报表
- [ ] API文档生成

### 6.4 第四阶段（优化完善）- 2周

- [ ] 性能优化
- [ ] 安全加固
- [ ] 自动化测试
- [ ] 部署文档

## 7. 成功指标

### 7.1 技术指标

- API可用性 >99.9%
- 平均响应时间 <2秒
- 错误率 <1%
- 测试覆盖率 >80%

### 7.2 业务指标

- 降低API成本 30%+
- 提高服务稳定性 50%+
- 减少开发集成时间 70%+
- 统一管理效率提升 80%+

## 8. 风险与对策

| 风险 | 影响 | 概率 | 对策 |
|------|------|------|------|
| API规范变更 | 高 | 中 | 版本化适配器、快速更新机制 |
| 密钥泄露 | 高 | 低 | 加密存储、最小权限、定期轮换 |
| 服务过载 | 中 | 中 | 限流、熔断、弹性扩容 |
| 成本超支 | 中 | 中 | 实时监控、预算告警、自动降级 |

## 9. 附录

### 9.1 术语表

- **Provider**：AI服务提供商（OpenAI、Azure等）
- **Key Pool**：API密钥池
- **Token**：内部访问令牌
- **Model Mapping**：模型名称映射
- **SSE**：Server-Sent Events，流式响应协议

### 9.2 参考资料

- OpenAI API文档：https://platform.openai.com/docs
- Azure OpenAI文档：https://learn.microsoft.com/azure/cognitive-services/openai/
- Anthropic API文档：https://docs.anthropic.com/claude/reference
- Symfony最佳实践：https://symfony.com/doc/current/best_practices.html

### 9.3 变更历史

| 版本 | 日期 | 作者 | 变更说明 |
|------|------|------|---------|
| 1.0 | 2024-01-20 | Team | 初始版本，整合多个PRD |
| 1.1 | 2024-01-21 | Team | 添加模块依赖关系 |

---

**文档状态**：✅ 已审核  
**下一步行动**：开始技术设计文档编写