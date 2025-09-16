<?php
/**
 * Portos International API Plugin Configuration
 * Configuración del plugin de APIs personalizadas
 * 
 * @author Claude Code - OrangeHRM Customization
 * @version 2.0
 * @since 2024
 */

namespace OrangeHRM\Portos\Config;

use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointAware;
use OrangeHRM\Framework\PluginConfigurationInterface;
use OrangeHRM\Portos\Api\v2\PortosAnnouncementsAPI;
use OrangeHRM\Portos\Api\v2\PortosComplianceAPI;
use OrangeHRM\Portos\Api\v2\PortosDashboardAPI;

class PortosAPIPluginConfiguration implements PluginConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        // Plugin initialization
    }

    /**
     * @inheritDoc
     */
    public function getEventSubscribers(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getEventListeners(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function registerServices(): void
    {
        // Register services if needed
    }

    /**
     * @inheritDoc
     */
    public function registerRoutes(): void
    {
        // API routes are registered via attributes
    }
}