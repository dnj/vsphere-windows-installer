<?php

namespace dnj\VsphereInstaller\Windows;

use dnj\VsphereInstaller\Customization as GlobalCustomization;

class Customization extends GlobalCustomization
{
    protected bool $systemRestore = false;
    protected bool $remoteDesktop = true;
    protected bool $antiSpyware = false;

    /**
     * @return static
     */
    public function enableRemoteDesktop(bool $enable = true): self
    {
        $this->remoteDesktop = $enable;

        return $this;
    }

    public function getRemoteDesktop(): bool
    {
        return $this->remoteDesktop;
    }

    /**
     * @return static
     */
    public function enableSystemRestore(bool $enable = true): self
    {
        $this->systemRestore = $enable;

        return $this;
    }

    public function getSystemRestore(): bool
    {
        return $this->systemRestore;
    }

    /**
     * @return static
     */
    public function enableAntiSpyware(bool $enable = true): self
    {
        $this->antiSpyware = $enable;

        return $this;
    }

    public function getAntiSpyware(): bool
    {
        return $this->antiSpyware;
    }
}
