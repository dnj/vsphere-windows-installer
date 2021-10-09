<?php

namespace dnj\VsphereInstaller\Windows;

use dnj\VsphereInstaller\CloneInstallerAbstract;

class CloneInstaller extends CloneInstallerAbstract
{
    protected ?string $customizer = GuestToolsCustomizer::class;
}
