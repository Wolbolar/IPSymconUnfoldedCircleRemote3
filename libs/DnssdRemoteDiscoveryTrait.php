<?php
declare(strict_types=1);

trait DnssdRemoteDiscoveryTrait
{
    private function GetDNSSD(): int
    {
        $mDNSInstanceIDs = IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}');
        if (empty($mDNSInstanceIDs)) {
            return 0;
        }
        return (int)$mDNSInstanceIDs[0];
    }

    public function SearchRemotes(): array
    {
        $mDNSInstanceID = $this->GetDNSSD();
        if ($mDNSInstanceID === 0) {
            return [];
        }
        return (array)ZC_QueryServiceType($mDNSInstanceID, '_uc-remote._tcp', 'local');
    }

    protected function GetRemoteInfo(array $devices): array
    {
        $mDNSInstanceID = $this->GetDNSSD();
        if ($mDNSInstanceID === 0) {
            return [];
        }

        $remote_info = [];
        $seen_hosts = [];

        foreach ($devices as $key => $remote) {
            $mDNS_name = $remote['Name'] ?? '';
            if ($mDNS_name === '') {
                continue;
            }

            $response = (array)ZC_QueryService($mDNSInstanceID, $mDNS_name, '_uc-remote._tcp', 'local.');
            foreach ($response as $data) {

                $name = '';
                $hostname = '';
                $ip4 = '';
                $ip6 = '';
                $port = 0;
                $model = '';
                $version = '';
                $ver_api = '';
                $https_port = '';

                if (isset($data['Name'])) {
                    $name = str_ireplace('._uc-remote._tcp.local.', '', (string)$data['Name']);
                }
                if (isset($data['Host'])) {
                    $hostname = str_ireplace('.local.', '', (string)$data['Host']);
                }
                if (isset($data['Port'])) {
                    $port = (int)$data['Port'];
                }

                if (isset($data['TXTRecords']) && is_array($data['TXTRecords'])) {
                    foreach ($data['TXTRecords'] as $record) {
                        $record = (string)$record;
                        if (str_starts_with($record, 'ver=')) $version = substr($record, 4);
                        if (str_starts_with($record, 'ver_api=')) $ver_api = substr($record, 8);
                        if (str_starts_with($record, 'model=')) $model = substr($record, 6);
                        if (str_starts_with($record, 'https_port=')) $https_port = substr($record, 11);
                    }
                }

                // IPv4 bevorzugen, IPv6 nur als Zusatzinfo
                if (isset($data['IPv4'][0])) {
                    $ip4 = (string)$data['IPv4'][0];
                }
                if (isset($data['IPv6'][0])) {
                    $ip6 = (string)$data['IPv6'][0];
                }

                // Wenn gar keine IP da ist -> skip
                if ($ip4 === '' && $ip6 === '') {
                    continue;
                }

                // Dedupe (hostname + ip4/ip6)
                $hostKey = ($hostname ?: $name) . '_' . ($ip4 ?: '-') . '_' . ($ip6 ?: '-');
                if (isset($seen_hosts[$hostKey])) {
                    continue;
                }
                $seen_hosts[$hostKey] = true;

                $remote_info[$key] = [
                    'name' => $name,
                    'hostname' => $hostname,
                    'host_ipv4' => $ip4,
                    'host_ipv6' => $ip6,
                    'port' => $port,
                    'id' => $name,
                    'model' => $model,
                    'version' => $version,
                    'ver_api' => $ver_api,
                    'https_port' => $https_port
                ];
            }
        }

        return $remote_info;
    }
}