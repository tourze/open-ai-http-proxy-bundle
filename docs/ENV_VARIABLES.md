# OpenAI HTTP Proxy Bundle 环境变量配置

本 Bundle 支持通过环境变量进行运行时配置，无需修改配置文件。

## 支持的环境变量

### 客户端选择器相关

| 环境变量 | 默认值 | 说明 |
|---------|--------|------|
| `OPENAI_PROXY_REFRESH_INTERVAL` | 300 | 客户端池刷新间隔（秒） |
| `OPENAI_PROXY_HEALTH_CHECK_TIMEOUT` | 2.0 | 健康检查超时时间（秒） |
| `OPENAI_PROXY_DEFAULT_STRATEGY` | weighted_score | 默认选择策略 |

**可选的选择策略：**
- `weighted_score` - 综合评分（推荐）
- `round_robin` - 轮询
- `random` - 随机选择
- `least_used` - 最少使用
- `best_performance` - 最佳性能
- `failover` - 故障转移

### 代理服务相关

| 环境变量 | 默认值 | 说明 |
|---------|--------|------|
| `OPENAI_PROXY_DEFAULT_TIMEOUT` | 30 | 默认请求超时时间（秒） |
| `OPENAI_PROXY_MAX_RETRIES` | 3 | 最大重试次数 |

## 使用示例

### 在 .env 文件中配置

```bash
# 客户端选择器配置
OPENAI_PROXY_REFRESH_INTERVAL=600  # 10分钟刷新一次
OPENAI_PROXY_HEALTH_CHECK_TIMEOUT=5.0  # 5秒健康检查超时
OPENAI_PROXY_DEFAULT_STRATEGY=best_performance  # 使用最佳性能策略

# 代理服务配置
OPENAI_PROXY_DEFAULT_TIMEOUT=60  # 60秒超时
OPENAI_PROXY_MAX_RETRIES=5  # 最多重试5次
```

### 通过 Docker 环境变量配置

```yaml
services:
  app:
    environment:
      OPENAI_PROXY_REFRESH_INTERVAL: 600
      OPENAI_PROXY_HEALTH_CHECK_TIMEOUT: 5.0
      OPENAI_PROXY_DEFAULT_STRATEGY: round_robin
      OPENAI_PROXY_DEFAULT_TIMEOUT: 45
      OPENAI_PROXY_MAX_RETRIES: 3
```

### 通过命令行设置

```bash
export OPENAI_PROXY_DEFAULT_STRATEGY=failover
export OPENAI_PROXY_MAX_RETRIES=10
php bin/console server:run
```

## 运行时覆盖

除了环境变量，某些配置还可以通过 HTTP 头在请求时覆盖：

- `X-Proxy-Strategy`: 覆盖选择策略
- `X-Proxy-Timeout`: 覆盖超时时间（秒）

```bash
curl -X POST https://api.example.com/proxy/v1/chat/completions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Proxy-Strategy: round_robin" \
  -H "X-Proxy-Timeout: 120" \
  -H "Content-Type: application/json" \
  -d '{"model": "gpt-4", "messages": [...]}'
```

## 注意事项

1. 环境变量优先级：请求头 > 环境变量 > 默认值
2. 所有时间相关的配置单位都是秒
3. 修改环境变量后需要重启应用才能生效
4. 建议在生产环境使用较长的刷新间隔以减少性能开销