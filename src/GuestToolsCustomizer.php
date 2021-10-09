<?php

namespace dnj\VsphereInstaller\Windows;

use dnj\phpvmomi\DataObjects\GuestAuthentication;
use dnj\phpvmomi\DataObjects\GuestProgramSpec;
use dnj\phpvmomi\DataObjects\NamePasswordAuthentication;
use dnj\phpvmomi\ManagedObjects\GuestProcessManager;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\VsphereInstaller\CloneCustomizerAbstract;
use dnj\VsphereInstaller\Contracts\ICloneInstaller;
use dnj\VsphereInstaller\Contracts\ICustomization;
use Exception;

class GuestToolsCustomizer extends CloneCustomizerAbstract
{
    protected GuestProcessManager $processManager;
    protected GuestAuthentication $auth;

    public function __construct(VirtualMachine $vm, ICustomization $customization, ICloneInstaller $installer)
    {
        parent::__construct($vm, $customization, $installer);

        $credentials = $installer->getCredentials();
        if (!$credentials) {
            throw new Exception('Windows credentials are required');
        }
        $this->auth = new NamePasswordAuthentication($credentials['username'], $credentials['password'], false);

        $processManagerRef = $this->api->getGuestOperationsManager()->processManager;
        if (!$processManagerRef) {
            throw new Exception('Cannot get processManager from GuestOperationsManager');
        }
        /**
         * @var GuestProcessManager
         */
        $processManager = $processManagerRef->init($this->api);
        $this->processManager = $processManager;
    }

    public function getAuth(): GuestAuthentication
    {
        return $this->auth;
    }

    public function start(): self
    {
        return $this
            ->waitForVmwareTools()
            ->setupTimezone()
            ->setupICMP()
            ->setupAutoUpdate()
            ->setupNetwork()
            ->setupPassword()
            ->setupRemoteDesktop()
            ->setupSystemRestore()
            ->setupAntiSpyware();
    }

    /**
     * @return static
     */
    public function setupTimezone(): self
    {
        $timezone = $this->customization->getTimezone();
        if (!$timezone) {
            return $this;
        }
        $this->runProcessInVM("TZUTIL /s \"{$timezone}\"");

        return $this;
    }

    /**
     * @return static
     */
    public function setupPassword(): self
    {
        $password = $this->customization->getPassword();
        try {
            $this->runProcessInVM("net user Administrator \"{$password}\"");
        } catch (\Exception $e) {
            if (false !== stripos($e->getMessage(), 'credentials')) {
                $this->auth->password = $password;
            }
        }
        $this->runProcessInVM("reg.exe add \"HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon\" /v AutoAdminLogon /t REG_DWORD /d 0 /f");

        return $this;
    }

    /**
     * @return static
     */
    public function setupICMP(): self
    {
        if (!$this->customization->getICMP()) {
            return $this;
        }
        $this->runProcessInVM('netsh advfirewall firewall add rule name=ICMP protocol=icmpv4 dir=in action=allow');

        return $this;
    }

    /**
     * @return static
     */
    public function setupAutoUpdate(): self
    {
        if ($this->customization->getAutoUpdate()) {
            return $this;
        }
        $this->runProcessInVM("reg.exe add \"HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\" /v AUOptions /t REG_DWORD /d 2 /f");

        return $this;
    }

    /**
     * @return static
     */
    public function setupRemoteDesktop(): self
    {
        if (!$this->customization instanceof Customization or !$this->customization->getRemoteDesktop()) {
            return $this;
        }
        $this->runProcessInVM("reg.exe add \"HKLM\SYSTEM\CurrentControlSet\Control\Terminal Server\" /v fDenyTSConnections /t REG_DWORD /d 0 /f");
        $this->runProcessInVM('netsh advfirewall firewall set rule group="remote desktop" new enable=yes');

        return $this;
    }

    /**
     * @return static
     */
    public function setupSystemRestore(): self
    {
        if (!$this->customization instanceof Customization or !$this->customization->getSystemRestore()) {
            return $this;
        }
        $this->runProcessInVM("reg.exe add \"HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\SystemRestore\" /v DisableSR /t REG_DWORD /d 1 /f");
        $this->runProcessInVM('sc config srservice start= disabled');
        $this->runProcessInVM('net stop srservice');

        return $this;
    }

    /**
     * @return static
     */
    public function setupAntiSpyware(): self
    {
        if (!$this->customization instanceof Customization or !$this->customization->getAntiSpyware()) {
            return $this;
        }
        $this->runProcessInVM("reg.exe add \"HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Microsoft\Windows Defender\" /v DisableAntiSpyware /t REG_DWORD /d 1 /f");

        return $this;
    }

    /**
     * @return static
     */
    public function setupNetwork(): self
    {
        $networkConfig = $this->customization->getNetwork();
        if (!$networkConfig) {
            return $this;
        }
        if (isset($networkConfig['ipv4']['dhcp']) and $networkConfig['ipv4']['dhcp']) {
            $this->runProcessInVM("netsh interface ipv4 set address \"{$networkConfig['identifier']}\" source=dhcp");
        } elseif (isset($networkConfig['ipv4']['address'], $networkConfig['ipv4']['gateway'], $networkConfig['ipv4']['netmask']) and $networkConfig['ipv4']['address']) {
            $this->runProcessInVM('netsh interface ipv4 set address'.
                " \"{$networkConfig['identifier']}\"".
                ' source=static'.
                " address={$networkConfig['ipv4']['address']}".
                " mask={$networkConfig['ipv4']['netmask']}".
                " gateway={$networkConfig['ipv4']['gateway']}".
                ' gwmetric=1'
            );
        }

        if (isset($networkConfig['dns-servers'])) {
            $this->runProcessInVM("netsh interface ipv4 set dns \"{$networkConfig['identifier']}\" source=static  address= ");
            $x = 1;
            foreach ($networkConfig['dns-servers'] as $dnsServer) {
                $this->runProcessInVM('netsh interface ipv4 add dnsservers'.
                    " \"{$networkConfig['identifier']}\"".
                    " address={$dnsServer}".
                    " index={$x}"
                );
                ++$x;
            }
        }

        return $this;
    }

    /**
     * @return static
     */
    public function waitForVmwareTools(int $timeout = 120): self
    {
        $start = time();
        while ((!$this->vm->guest or 'guestToolsRunning' !== $this->vm->guest->toolsRunningStatus) and (time() - $start < $timeout or 0 === $timeout)) {
            sleep(1);
            $this->vm->reloadFromAPI();
        }
        if (!$this->vm->guest or 'guestToolsRunning' !== $this->vm->guest->toolsRunningStatus) {
            throw new Exception('guest tools not comming up');
        }

        return $this;
    }

    public function runProcessInVM(string $cmd, int $timeout = 60): void
    {
        $start = time();
        do {
            try {
                $process = new GuestProgramSpec('C:\\Windows\\System32\\cmd.exe', '/c '.$cmd);
                $pid = $this->processManager->_StartProgramInGuest($this->vm->ref(), $this->auth, $process);
                do {
                    sleep(1);
                    $process = $this->processManager->_ListProcessesInGuest($this->vm->ref(), $this->auth, [$pid])[0] ?? null;
                    if (null === $process) {
                        throw new Exception('Process get lost');
                    }
                    if (null !== $process->exitCode) {
                        if (0 !== $process->exitCode) {
                            throw new Exception("Process exit code: {$process->exitCode}");
                        }

                        return;
                    }
                } while (0 === $timeout or (time() - $start < $timeout));
            } catch (\Exception $e) {
                if ('The operation is not allowed in the current state.' === $e->getMessage()) {
                    sleep(10);
                } else {
                    throw $e;
                }
            }
        } while (0 === $timeout or (time() - $start < $timeout));
    }
}
