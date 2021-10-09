<?php

namespace dnj\VsphereInstaller\Windows\Tests;

use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\VsphereInstaller\Windows\CloneInstaller;
use dnj\VsphereInstaller\Windows\Customization;
use dnj\VsphereInstaller\Windows\GuestToolsCustomizer;

class GuestToolsCustomizerTest extends TestCase
{
    public function test(): void
    {
        $api = $this->getAPI();
        $vmID = $this->getVmID();
        $target = (new VirtualMachine($api))->byID($vmID);

        $customization = new Customization();
        $customization->setNetwork([
            'identifier' => 'Ethernet0',
            'ipv4' => [
                'dhcp' => false,
                'address' => '10.10.0.251',
                'netmask' => '255.255.0.0',
                'gateway' => '10.10.0.1',
            ],
            'dns-servers' => [
                '213.133.98.98',
                '8.8.4.4',
            ],
        ]);
        $customization->setTimezone('Iran Standard Time');
        $customization->setPassword('new-password');
        $customization->enableICMP();
        $customization->enableRemoteDesktop();
        $customization->enableAutoUpdate(false);
        $customization->enableSystemRestore(false);
        $customization->enableAntiSpyware(false);

        $installer = new CloneInstaller();
        $installer->setAPI($api);
        $installer->setCustomization($customization);
        $installer->setCredentials('Administrator', 'current-password'); 

        $customizer = new GuestToolsCustomizer($target, $customization, $installer);
        $customizer->start();

        $this->assertTrue(true);
    }
}
