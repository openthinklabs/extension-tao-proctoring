<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA;
 *
 *
 */
namespace oat\taoProctoring\model\delivery;

use oat\taoDelivery\model\DeliverySecurePluginsService as BaseDeliveryPluginService;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoProctoring\model\ProctorService;

class DeliverySecurePluginsService extends BaseDeliveryPluginService
{
    private $proctoredSecurePlugins = [
        'blurPause',
        'disableCommands',
        'preventCopy',
        'preventScreenshot',
        'fullscreen'
    ];

    public function getPlugins()
    {
        $deliveryExecutionUri = \Context::getInstance()->getRequest()->getParameter('deliveryExecution');

        $filterPlugins = $this->deliverySecurePlugins;
        if ($deliveryExecutionUri) {
            $deliveryExecution = ServiceProxy::singleton()->getDeliveryExecution($deliveryExecutionUri);
            $filterPlugins = $this->isProctoredDelivery($deliveryExecution->getDelivery()) ? $this->proctoredSecurePlugins : $this->deliverySecurePlugins;
        }

        return $filterPlugins;
    }

    /**
     * Check whether secure plugins must be used.
     * @param \core_kernel_classes_Resource $delivery
     * @return bool
     */
    private function isProctoredDelivery(\core_kernel_classes_Resource $delivery)
    {
        $hasProctor = $delivery->getOnePropertyValue($this->getProperty(ProctorService::ACCESSIBLE_PROCTOR));
        $result = $hasProctor instanceof \core_kernel_classes_Resource &&
            $hasProctor->getUri() == ProctorService::ACCESSIBLE_PROCTOR_ENABLED;
        return $result;
    }
}
