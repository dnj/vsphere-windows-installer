<?php

namespace dnj\VsphereInstaller\Windows\Tests;

use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\VsphereInstaller\Windows\IsoInstaller;

class IsoInstallerTest extends TestCase
{
    public function testInstall(): void
    {
        $api = $this->getAPI();
        $vmID = $this->getVmID();
        $target = (new VirtualMachine($api))->byID($vmID);

        $isoInstaller = new IsoInstaller();
        $isoInstaller->setAPI($api);
        $isoInstaller->setTarget($target);
        $isoInstaller->setIsoFiles([
            'http://iso.jeyserver.com/windows-server-2019-R2.iso',
            new \dnj\Filesystem\Local\File('/home/composer/dnj/windows-isomaker/unattend.iso'),
        ]);
        $isoInstaller->install();
    }
}
