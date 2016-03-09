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
 * @copyright (c) 2016 by Herzog August Bibliothek Wolfenbüttel
 * @license   http://www.gnu.org/licenses/gpl.txt GNU General Public License v3 or higher
 */

use PHPUnit_Framework_TestCase as TestCase;

require_once __DIR__ . '/../lib/Auth/Source/Pica.php';

/**
 * Unit tests for the Pica authentication source.
 *
 * @author    David Maus <maus@hab.de>
 * @copyright (c) 2016 by Herzog August Bibliothek Wolfenbüttel
 * @license   http://www.gnu.org/licenses/gpl.txt GNU General Public License v3 or higher
 */
class PicaTest extends TestCase
{
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Unknown pica authentication module
     */
    public function testExceptionOnUnknownPicaAuthenticationModule ()
    {
        $config = SimpleSAML_Configuration::loadFromArray(array('module' => 'foobar'));
        $source = $this
                ->getMockBuilder('sspmod_pica_Auth_Source_Pica')
                ->disableOriginalConstructor()
                ->setMethods(null)
                ->getMock();
        $source->createAuthenticationModuleFactory($config);
    }

    /**
     * @expectedException SimpleSAML_Error_AuthSource
     */
    public function testExceptionOnAuthenticationModuleRuntimeError ()
    {
        $module = $this->getMockForAbstractClass('HAB\Pica\Auth\AuthenticationInterface');
        $module
            ->expects($this->any())
            ->method('authenticate')
            ->will($this->throwException(new RuntimeException()));

        $source = $this
                ->getMockBuilder('sspmod_pica_Auth_Source_Pica')
                ->disableOriginalConstructor()
                ->setMethods(array('getAuthenticationModule'))
                ->getMock();
        $source
            ->expects($this->any())
            ->method('getAuthenticationModule')
            ->will($this->returnValue($module));

        $method = new ReflectionMethod($source, 'login');
        $method->setAccessible(true);
        $method->invoke($source, 'foo', 'bar');
    }
}