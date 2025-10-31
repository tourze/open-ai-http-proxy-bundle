# OpenAI HTTP Proxy Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/open-ai-http-proxy-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/open-ai-http-proxy-bundle)
[![Build Status](https://img.shields.io/travis/tourze/open-ai-http-proxy-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/open-ai-http-proxy-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/open-ai-http-proxy-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/open-ai-http-proxy-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/open-ai-http-proxy-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/open-ai-http-proxy-bundle)

A Symfony bundle that provides a proxy layer for OpenAI API compatible services, allowing flexible routing, fallback, and model mapping between different AI providers.

## Features

- Proxy requests to multiple OpenAI-compatible API providers
- Automatic fallback when primary providers fail
- Model mapping between different providers
- Token-based authentication and authorization
- Rate limiting support
- Support for streaming responses
- Configurable retry and timeout policies
- Comprehensive metrics tracking

## Installation

```bash
composer require tourze/open-ai-http-proxy-bundle
```

## Quick Start

### Basic Configuration

```yaml
# config/packages/open_ai_http_proxy.yaml
open_ai_http_proxy:
    providers:
        openai:
            base_url: https://api.openai.com
            api_key: '%env(OPENAI_API_KEY)%'
        anthropic:
            base_url: https://api.anthropic.com
            api_key: '%env(ANTHROPIC_API_KEY)%'
```

### Usage

The bundle automatically registers proxy endpoints for OpenAI API:

```php
// Making requests through the proxy
POST /proxy/v1/chat/completions
Authorization: Bearer your-token

{
    "model": "gpt-3.5-turbo",
    "messages": [
        {"role": "user", "content": "Hello!"}
    ]
}
```

### Model Mapping

Configure model mapping between providers:

```php
use Tourze\OpenAiHttpProxyBundle\Service\ModelMappingService;

// In your service
public function __construct(private ModelMappingService $modelMapping)
{
    // Map gpt-4 to claude-3 for Anthropic provider
    $this->modelMapping->setProviderMapping('anthropic', 'gpt-4', 'claude-3-opus');
}
```

### Client Selection

The bundle supports multiple client selection strategies:

```php
use Tourze\OpenAiHttpProxyBundle\Service\ClientSelectorService;

// The service automatically selects the best available client
// based on health checks and configured strategy
$client = $this->clientSelector->selectClientWithFallback('gpt-4', $context);
```

## Available Endpoints

- `/proxy/v1/chat/completions` - Chat completions (supports streaming)
- `/proxy/v1/completions` - Text completions
- `/proxy/v1/embeddings` - Text embeddings
- `/proxy/v1/models` - List available models
- `/proxy/status` - Check proxy status

## Documentation

- [Configuration Guide](docs/configuration.md)
- [API Documentation](docs/api.md)
- [Advanced Usage](docs/advanced.md)

## Testing

```bash
./vendor/bin/phpunit packages/open-ai-http-proxy-bundle/tests
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.