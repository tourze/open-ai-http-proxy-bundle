<?php

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\OpenAiHttpProxyBundle\Controller\ChatCompletionsController;
use Tourze\OpenAiHttpProxyBundle\Controller\CompletionsController;
use Tourze\OpenAiHttpProxyBundle\Controller\EmbeddingsController;
use Tourze\OpenAiHttpProxyBundle\Controller\ModelsController;
use Tourze\OpenAiHttpProxyBundle\Controller\StatusController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
final class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->autoload();
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addCollection($this->controllerLoader->load(ChatCompletionsController::class));
        $collection->addCollection($this->controllerLoader->load(CompletionsController::class));
        $collection->addCollection($this->controllerLoader->load(EmbeddingsController::class));
        $collection->addCollection($this->controllerLoader->load(ModelsController::class));
        $collection->addCollection($this->controllerLoader->load(StatusController::class));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }
}
