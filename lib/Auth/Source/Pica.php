<?php

/**
 * This file is part of SimpleSAMLphp Module Pica.
 *
 * SimpleSAMLphp Module Pica is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SimpleSAMLphp Module Pica is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SimpleSAMLphp Module Pica.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    David Maus <maus@hab.de>
 * @copyright (c) 2015 by Herzog August Bibliothek Wolfenbüttel
 * @license   http://www.gnu.org/licenses/gpl.txt GNU General Public License v3 or higher
 */

use HAB\Pica\Auth;

/**
 * Authentication source for Pica-based library systems.
 *
 * @author    David Maus <maus@hab.de>
 * @copyright (c) 2015 by Herzog August Bibliothek Wolfenbüttel
 * @license   http://www.gnu.org/licenses/gpl.txt GNU General Public License v3 or higher
 */
class sspmod_pica_Auth_Source_Pica extends sspmod_core_Auth_UserPassBase
{
    /**
     * Error message settings.
     *
     * @var array
     */
    private $message;

    /**
     * Factory function for authentication module.
     *
     * @var callable
     */
    private $factory;

    /**
     * {@inheritDoc}
     */
    public function __construct ($info, $config)
    {
        parent::__construct($info, $config);
        if (!array_key_exists('pica', $config)) {
            throw new Exception('Pica authentication source configuration missing: [pica]');
        }

        $configuration = SimpleSAML_Configuration::loadFromArray($config['pica']);
        $this->factory = $this->createAuthenticationModuleFactory($configuration);
        $this->message = $configuration->getArray('errors', array());
        $this->attrmap = $configuration->getArray('attrmap', array());
    }

    /**
     * {@inheritDoc}
     */
    protected function login ($username, $password)
    {
        $module = $this->getAuthenticationModule();
        try {
            $attributes = $module->authenticate($username, $password);
        } catch (RuntimeException $error) {
            $this->handleAuthenticationModuleRuntimeError($error);
            throw new SimpleSAML_Error_AuthSource('pica', $error->getMessage(), $error);
        }
        if ($attributes === false) {
            throw new SimpleSAML_Error_Error('WRONGUSERPASS');
        }
        return $this->normalize($attributes);
    }

    /**
     * Return authentication module factory function.
     *
     * @param  SimpleSAML_Configuration $config
     * @return callable
     */
    public function createAuthenticationModuleFactory (SimpleSAML_Configuration $config)
    {
        $module = $config->getString('module');
        switch ($module) {
        case 'lbs4-webservice':
            $serviceUrl = $config->getString('serviceUrl');
            $catalogNumber = $config->getInteger('catalogNumber');
            $lbsUserNumber = $config->getInteger('lbsUserNumber');
            $factory = function () use ($serviceUrl, $catalogNumber, $lbsUserNumber) {
                return new Auth\LBSAuthentication($serviceUrl, $catalogNumber, $lbsUserNumber);
            };
            break;
        case 'loan3-web':
            $serviceUrl = $config->getString('serviceUrl');
            $factory = function () use ($serviceUrl) {
                return new Auth\LOAN3WebAuthentication($serviceUrl);
            };
            break;
        default:
            throw new Exception("Unknown pica authentication module: '{$module}'");
        }
    }

    /**
     * Handle authentication module runtime error.
     *
     * @param  RuntimeException $error
     * @return void
     */
    protected function handleAuthenticationModuleRuntimeError (RuntimeException $error)
    {
        if ($this->message) {
            $defaults = array('to' => null, 'from' => null, 'subject' => 'Runtime error in Pica authentication module');
            $settings = array_replace($defaults, array_intersect_key($this->message, $defaults));

            $message = new SimpleSAML_XHTML_EMail($settings['to'], $settings['subject'], $settings['from']);
            $message->setBody(sprintf('<h1>%s</h1><pre>%s</pre>', $error->getMessage(), $error->getTraceAsString()));
            $message->send();
        }
    }

    /**
     * Return normalized attributes.
     *
     * @param  array $attributes
     * @return array
     */
    private function normalize (array $attributes)
    {
        $normalized = array();
        foreach ($this->attrmap as $from => $to) {
            if (array_key_exists($from, $attributes)) {
                $normalized[$to] = (array)$attributes[$from];
            }
        }
        return $normalized;
    }

    /**
     * Return pica authentication module.
     *
     * @return Auth\AuthenticationInterface
     */
    protected function getAuthenticationModule ()
    {
        return call_user_func($this->factory);
    }
}