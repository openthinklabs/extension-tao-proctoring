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
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA ;
 */
/**
 * @author Jean-Sébastien Conan <jean-sebastien.conan@vesperiagroup.com>
 */
define([
    'jquery',
    'i18n',
    'helpers',
    'layout/loading-bar',
    'ui/datatable'
], function ($, __, helpers, loadingBar) {
    'use strict';

    /**
     * The polling delay used to refresh the list
     * @type {Number}
     */
    var refreshPolling = 60 * 1000; // once per minute

    /**
     * The CSS scope
     * @type {String}
     */
    var cssScope = '.delivery-manager';

    // the page is always loading data when starting
    loadingBar.start();

    /**
     * Controls the ProctorDelivery index page
     *
     * @type {{start: Function}}
     */
    var proctorDeliveryIndexCtlr = {
        /**
         * Entry point of the page
         */
        start : function start() {
            var $list = $(cssScope + ' .list');
            var dataset = $list.data('set');
            var deliveryId = $list.data('id');
            var assignUrl = helpers._url('testTakers', 'ProctorDelivery', 'taoProctoring', {id : deliveryId});
            var serviceUrl = helpers._url('deliveryTestTakers', 'ProctorDelivery', 'taoProctoring', {id : deliveryId});

            $list
                .on('query.datatable', function() {
                    loadingBar.start();
                })
                .on('load.datatable', function() {
                    loadingBar.stop();
                })
                .datatable({
                    url: serviceUrl,
                    data: dataset,
                    filter: true,
                    status: {
                        empty: __('No assigned test takers'),
                        available: __('Assigned test takers'),
                        loading: __('Loading')
                    },
                    tools: [{
                        id: 'assign',
                        icon: 'add',
                        title: __('Assign test takers to this delivery'),
                        label: __('Add test takers'),
                        action: function() {
                            location.href = assignUrl;
                        }
                    }],
                    actions: [{
                        id: 'validate',
                        icon: 'checkbox-checked',
                        title: __('Validate the request'),
                        action: function() {
                            alert('validate')
                        }
                    }, {
                        id: 'lock',
                        icon: 'lock',
                        title: __('Lock the test taker'),
                        action: function() {
                            alert('lock')
                        }
                    }, {
                        id: 'comment',
                        icon: 'document',
                        title: __('Write comment'),
                        action: function() {
                            alert('comment')
                        }
                    }],
                    selectable: true,
                    model: [{
                        id: 'firstname',
                        label: __('First name'),
                        sortable: true
                    }, {
                        id: 'lastname',
                        label: __('Last name'),
                        sortable: true
                    }, {
                        id: 'company',
                        label: __('Company name'),
                        sortable: true
                    }, {
                        id: 'status',
                        label: __('Status'),
                        sortable: true
                    }]
                });
        }
    };

    return proctorDeliveryIndexCtlr;
});
