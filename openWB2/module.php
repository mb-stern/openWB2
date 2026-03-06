<?php

class openWB2 extends IPSModuleStrict
{
    private const MQTT_CLIENT_SPLITTER_GUID = '{EE0D345A-CF31-428A-A613-33CE98E752DD}';
    private const MQTT_CLIENT_TX = '{97475B04-67C3-A74D-C970-E9409B0EFA1D}';
    private const MQTT_CLIENT_RX = '{DBDA9DF7-5D04-F49D-370A-2B9153D00D9B}';

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('BaseTopic', 'openWB');
        $this->RegisterPropertyInteger('ChargePointID', 0);

        $this->RegisterProfiles();

        if ($this->RegisterVariableInteger('LPSoC', 'LP SoC', '~Intensity.100', 10)) {
            $this->SetValue('LPSoC', 0);
        }
        if ($this->RegisterVariableInteger('LPProSoC', 'LP Pro SoC', '~Intensity.100', 20)) {
            $this->SetValue('LPProSoC', 0);
        }
        if ($this->RegisterVariableInteger('LPConfiguredCurrent', 'LP EVSE Current', 'OWB.Ampere', 30)) {
            $this->SetValue('LPConfiguredCurrent', 0);
        }

        if ($this->RegisterVariableFloat('LPPhaseCurrent1', 'LP Phase 1 Current', '~Ampere', 40)) {
            $this->SetValue('LPPhaseCurrent1', 0.0);
        }
        if ($this->RegisterVariableFloat('LPPhaseCurrent2', 'LP Phase 2 Current', '~Ampere', 50)) {
            $this->SetValue('LPPhaseCurrent2', 0.0);
        }
        if ($this->RegisterVariableFloat('LPPhaseCurrent3', 'LP Phase 3 Current', '~Ampere', 60)) {
            $this->SetValue('LPPhaseCurrent3', 0.0);
        }

        if ($this->RegisterVariableFloat('LPVoltage1', 'LP Phase 1 Voltage', '~Volt', 70)) {
            $this->SetValue('LPVoltage1', 0.0);
        }
        if ($this->RegisterVariableFloat('LPVoltage2', 'LP Phase 2 Voltage', '~Volt', 80)) {
            $this->SetValue('LPVoltage2', 0.0);
        }
        if ($this->RegisterVariableFloat('LPVoltage3', 'LP Phase 3 Voltage', '~Volt', 90)) {
            $this->SetValue('LPVoltage3', 0.0);
        }

        if ($this->RegisterVariableFloat('LPPower', 'LP Charging Power', '~Power', 100)) {
            $this->SetValue('LPPower', 0.0);
        }
        if ($this->RegisterVariableInteger('LPPhasesInUse', 'LP Phases in Use', '', 110)) {
            $this->SetValue('LPPhasesInUse', 0);
        }

        if ($this->RegisterVariableBoolean('LPChargeState', 'LP Charge State', 'OWB.ChargeState', 120)) {
            $this->SetValue('LPChargeState', false);
        }
        if ($this->RegisterVariableBoolean('LPPlugState', 'LP Plug State', 'OWB.PlugState', 130)) {
            $this->SetValue('LPPlugState', false);
        }
        if ($this->RegisterVariableBoolean('LPChargePointEnabled', 'LP Chargepoint Enabled', 'OWB.ChargePointEnabled', 140)) {
            $this->SetValue('LPChargePointEnabled', true);
        }
        $this->EnableAction('LPChargePointEnabled');

        if ($this->RegisterVariableInteger('LPState', 'LP State', 'OWB.LPState', 150)) {
            $this->SetValue('LPState', 0);
        }

        if ($this->RegisterVariableInteger('LPFaultState', 'LP Fault State', '', 160)) {
            $this->SetValue('LPFaultState', 0);
        }
        if ($this->RegisterVariableString('LPFaultString', 'LP Fault String', '', 170)) {
            $this->SetValue('LPFaultString', '');
        }
        if ($this->RegisterVariableString('LPStateString', 'LP State String', '', 180)) {
            $this->SetValue('LPStateString', '');
        }
        if ($this->RegisterVariableString('LPVehicleName', 'LP Vehicle Name', '', 190)) {
            $this->SetValue('LPVehicleName', '');
        }
        if ($this->RegisterVariableString('LPRFID', 'LP RFID', '', 200)) {
            $this->SetValue('LPRFID', '');
        }

        if ($this->RegisterVariableFloat('LPDailyImported', 'LP Daily Imported', '~Electricity', 210)) {
            $this->SetValue('LPDailyImported', 0.0);
        }
        if ($this->RegisterVariableFloat('LPImported', 'LP Imported', '~Electricity', 220)) {
            $this->SetValue('LPImported', 0.0);
        }

        if ($this->RegisterVariableInteger('LPCurrent', 'LP Current', 'OWB.Ampere', 300)) {
            $this->SetValue('LPCurrent', 6);
        }
        $this->EnableAction('LPCurrent');

        if ($this->RegisterVariableInteger('LPChargeMode', 'LP Charge Mode', 'OWB.ChargeMode', 310)) {
            $this->SetValue('LPChargeMode', 0);
        }
        $this->EnableAction('LPChargeMode');

        if ($this->RegisterVariableInteger('LPChargeLimitation', 'LP Charge Limitation', 'OWB.ChargeLimitation', 320)) {
            $this->SetValue('LPChargeLimitation', 0);
        }
        $this->EnableAction('LPChargeLimitation');

        if ($this->RegisterVariableInteger('LPSoCToChargeTo', 'LP SoC To Charge To', '~Intensity.100', 330)) {
            $this->SetValue('LPSoCToChargeTo', 0);
        }
        $this->EnableAction('LPSoCToChargeTo');

        if ($this->RegisterVariableInteger('LPEnergyToCharge', 'LP Energy To Charge', 'OWB.EnergyToCharge', 340)) {
            $this->SetValue('LPEnergyToCharge', 1);
        }
        $this->EnableAction('LPEnergyToCharge');

        if ($this->RegisterVariableInteger('LPResetDirectCharge', 'LP Reset Direct Charge', 'OWB.ResetDirectCharge', 350)) {
            $this->SetValue('LPResetDirectCharge', 1);
        }
        $this->EnableAction('LPResetDirectCharge');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $baseTopic = trim($this->ReadPropertyString('BaseTopic'));
        $cpBase = $this->GetChargePointBaseTopic();

        if ($baseTopic === '' || $cpBase === '') {
            $this->SetReceiveDataFilter('.*');
            $this->SetSummary('openWB / ChargePoint ?');
            return;
        }

        $patterns = [
            preg_quote($cpBase, '/'),
            preg_quote(rtrim($baseTopic, '/') . '/simpleAPI/set', '/')
        ];

        $this->SetReceiveDataFilter('.*(' . implode('|', $patterns) . ').*');
        $this->SetSummary($cpBase);

        $this->SubscribeTopics();
    }

    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type'      => 'connect',
            'moduleIds' => [self::MQTT_CLIENT_SPLITTER_GUID]
        ]);
    }

    public function ReceiveData(string $JSONString): void
    {
        $packet = json_decode($JSONString, true);
        if (!is_array($packet)) {
            return;
        }

        $message = $this->ExtractTopicPayload($packet);
        if ($message === null) {
            return;
        }

        $topic = $message['Topic'];
        $payload = $message['Payload'];

        $cpBase = $this->GetChargePointBaseTopic();
        if ($cpBase === '') {
            return;
        }

        switch ($topic) {
            case $cpBase . '/soc/soc':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPSoC', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/pro_soc':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPProSoC', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/evse_current':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPConfiguredCurrent', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/currents/1':
                $this->SetFloatIfNumeric('LPPhaseCurrent1', $payload);
                break;
            case $cpBase . '/currents/2':
                $this->SetFloatIfNumeric('LPPhaseCurrent2', $payload);
                break;
            case $cpBase . '/currents/3':
                $this->SetFloatIfNumeric('LPPhaseCurrent3', $payload);
                break;

            case $cpBase . '/voltages/1':
                $this->SetFloatIfNumeric('LPVoltage1', $payload);
                break;
            case $cpBase . '/voltages/2':
                $this->SetFloatIfNumeric('LPVoltage2', $payload);
                break;
            case $cpBase . '/voltages/3':
                $this->SetFloatIfNumeric('LPVoltage3', $payload);
                break;

            case $cpBase . '/power':
                $this->SetFloatIfNumeric('LPPower', $payload);
                break;

            case $cpBase . '/phases_in_use':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPPhasesInUse', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/charge_state':
                $this->SetValue('LPChargeState', $this->ToBool($payload));
                $this->UpdateLPState();
                break;

            case $cpBase . '/plug_state':
                $this->SetValue('LPPlugState', $this->ToBool($payload));
                $this->UpdateLPState();
                break;

            case $cpBase . '/manual_lock':
                $this->SetValue('LPChargePointEnabled', !$this->ToBool($payload));
                break;

            case $cpBase . '/fault_state':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPFaultState', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/fault_str':
                $this->SetValue('LPFaultString', $this->PayloadToString($payload));
                break;

            case $cpBase . '/state_str':
                $this->SetValue('LPStateString', $this->PayloadToString($payload));
                break;

            case $cpBase . '/vehicle_name':
                $this->SetValue('LPVehicleName', $this->PayloadToString($payload));
                break;

            case $cpBase . '/rfid':
                $this->SetValue('LPRFID', $this->PayloadToString($payload));
                break;

            case $cpBase . '/daily_imported':
                $this->SetFloatIfNumeric('LPDailyImported', $payload);
                break;

            case $cpBase . '/imported':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPImported', ((float) $payload) / 1000);
                }
                break;

            case $cpBase . '/chargemode':
                $this->SetValue('LPChargeMode', $this->MapChargeModeStringToInt($this->PayloadToString($payload)));
                break;

            case $cpBase . '/instant_charging_limit':
                $this->SetValue('LPChargeLimitation', $this->MapLimitTypeStringToInt($this->PayloadToString($payload)));
                break;

            case $cpBase . '/instant_charging_limit_soc':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPSoCToChargeTo', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/instant_charging_limit_amount':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPEnergyToCharge', (int) round((float) $payload));
                }
                break;

            case $cpBase . '/chargecurrent':
            case $cpBase . '/charging_current':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPCurrent', (int) round((float) $payload));
                }
                break;
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'LPChargePointEnabled':
                $this->PublishSetTopic(
                    'chargepoint/' . $this->ReadPropertyInteger('ChargePointID') . '/chargepoint_lock',
                    $Value ? 'false' : 'true'
                );
                $this->SetValue('LPChargePointEnabled', (bool) $Value);
                break;

            case 'LPCurrent':
                $current = max(6, min(32, (int) $Value));
                $this->PublishSetTopic(
                    'chargepoint/' . $this->ReadPropertyInteger('ChargePointID') . '/chargecurrent',
                    (string) $current
                );
                $this->SetValue('LPCurrent', $current);
                break;

            case 'LPChargeMode':
                $modeString = $this->MapChargeModeIntToString((int) $Value);
                $this->PublishSetTopic(
                    'chargepoint/' . $this->ReadPropertyInteger('ChargePointID') . '/chargemode',
                    $modeString
                );
                $this->SetValue('LPChargeMode', (int) $Value);
                break;

            case 'LPChargeLimitation':
                $limitType = $this->MapLimitTypeIntToString((int) $Value);
                $this->PublishSetTopic('instant_charging_limit', $limitType);
                $this->SetValue('LPChargeLimitation', (int) $Value);
                break;

            case 'LPSoCToChargeTo':
                $soc = max(0, min(100, (int) $Value));
                $this->PublishSetTopic('instant_charging_limit_soc', (string) $soc);
                $this->SetValue('LPSoCToChargeTo', $soc);
                break;

            case 'LPEnergyToCharge':
                $energy = max(1, min(50, (int) $Value));
                $this->PublishSetTopic('instant_charging_limit_amount', (string) $energy);
                $this->SetValue('LPEnergyToCharge', $energy);
                break;

            case 'LPResetDirectCharge':
                $this->PublishSetTopic('instant_charging_limit', 'none');
                $this->PublishSetTopic('instant_charging_limit_soc', '0');
                $this->PublishSetTopic('instant_charging_limit_amount', '1');

                $this->SetValue('LPChargeLimitation', 0);
                $this->SetValue('LPSoCToChargeTo', 0);
                $this->SetValue('LPEnergyToCharge', 1);
                $this->SetValue('LPResetDirectCharge', 1);
                break;

            default:
                throw new InvalidArgumentException('Invalid Ident: ' . $Ident);
        }
    }

    private function SubscribeTopics(): void
    {
        $cpBase = $this->GetChargePointBaseTopic();
        if ($cpBase === '') {
            return;
        }

        $topics = [
            $cpBase . '/#',
            rtrim($this->ReadPropertyString('BaseTopic'), '/') . '/simpleAPI/#'
        ];

        foreach ($topics as $topic) {
            $this->SendMQTTClientCommand([
                'Function' => 'Subscribe',
                'Topic'    => $topic
            ]);
        }
    }

    private function PublishSetTopic(string $relativeTopic, string $payload, bool $retain = false): void
    {
        $baseTopic = rtrim($this->ReadPropertyString('BaseTopic'), '/');
        $fullTopic = $baseTopic . '/simpleAPI/set/' . ltrim($relativeTopic, '/');

        $this->SendMQTTClientCommand([
            'Function' => 'Publish',
            'Topic'    => $fullTopic,
            'Payload'  => $payload,
            'Retain'   => $retain ? 1 : 0
        ]);
    }

    private function SendMQTTClientCommand(array $command): void
    {
        $buffer = json_encode($command, JSON_UNESCAPED_SLASHES);
        if ($buffer === false) {
            return;
        }

        $data = [
            'DataID' => self::MQTT_CLIENT_TX,
            'Buffer' => utf8_encode($buffer)
        ];

        $this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    private function ExtractTopicPayload(array $packet): ?array
    {
        if (isset($packet['Topic']) && array_key_exists('Payload', $packet)) {
            return [
                'Topic'   => (string) $packet['Topic'],
                'Payload' => $packet['Payload']
            ];
        }

        if (!isset($packet['Buffer'])) {
            return null;
        }

        $buffer = $packet['Buffer'];

        if (is_string($buffer)) {
            $decodedCandidates = [];

            $decodedCandidates[] = $buffer;

            if (ctype_xdigit($buffer) && (strlen($buffer) % 2) === 0) {
                $hex = @hex2bin($buffer);
                if ($hex !== false) {
                    $decodedCandidates[] = $hex;
                }
            }

            $utf8 = @utf8_decode($buffer);
            if (is_string($utf8) && $utf8 !== '') {
                $decodedCandidates[] = $utf8;
            }

            foreach ($decodedCandidates as $candidate) {
                $json = json_decode($candidate, true);
                if (!is_array($json)) {
                    continue;
                }

                if (isset($json['Topic']) && array_key_exists('Payload', $json)) {
                    return [
                        'Topic'   => (string) $json['Topic'],
                        'Payload' => $json['Payload']
                    ];
                }
            }
        }

        return null;
    }

    private function GetChargePointBaseTopic(): string
    {
        $baseTopic = trim($this->ReadPropertyString('BaseTopic'));
        $chargePointID = $this->ReadPropertyInteger('ChargePointID');

        if ($baseTopic === '') {
            return '';
        }

        return rtrim($baseTopic, '/') . '/simpleAPI/chargepoint/' . $chargePointID;
    }

    private function UpdateLPState(): void
    {
        $plugged = $this->GetValue('LPPlugState');
        $charging = $this->GetValue('LPChargeState');

        if (!$plugged) {
            $this->SetValue('LPState', 0);
            return;
        }

        if ($charging) {
            $this->SetValue('LPState', 2);
            return;
        }

        $this->SetValue('LPState', 1);
    }

    private function RegisterProfiles(): void
    {
        $this->RegisterProfileIntegerEx('OWB.ChargeLimitation', 'Power', '', '', [
            [0, 'Off', '', -1],
            [1, 'kWh charge', '', -1],
            [2, 'SoC charge', '', -1]
        ]);

        $this->RegisterProfileIntegerEx('OWB.ResetDirectCharge', 'Power', '', '', [
            [1, 'Reset', '', -1]
        ]);

        $this->RegisterProfileInteger('OWB.Ampere', 'Electricity', '', ' A', 0, 32, 1);
        $this->RegisterProfileInteger('OWB.EnergyToCharge', 'Electricity', '', ' kWh', 1, 50, 1);

        $this->RegisterProfileBooleanEx('OWB.PlugState', 'Car', '', '', [
            [false, 'Free', '', 0xFF0000],
            [true, 'Plugged', '', 0x00FF00]
        ]);

        $this->RegisterProfileBooleanEx('OWB.ChargeState', 'Car', '', '', [
            [false, 'Off', '', 0xFF0000],
            [true, 'Charge', '', 0x00FF00]
        ]);

        $this->RegisterProfileBooleanEx('OWB.ChargePointEnabled', 'Car', '', '', [
            [false, 'Locked', '', 0xFF0000],
            [true, 'Open', '', 0x00FF00]
        ]);

        $this->RegisterProfileIntegerEx('OWB.LPState', 'Information', '', '', [
            [0, 'Free', '', 0x00FF00],
            [1, 'Blocked', '', 0xFFFF00],
            [2, 'Charge', '', 0xFF0000]
        ]);

        $this->RegisterProfileIntegerEx('OWB.ChargeMode', 'Car', '', '', [
            [0, 'Instant', '', -1],
            [1, 'PV', '', -1],
            [2, 'Eco', '', -1],
            [3, 'Stop', '', -1],
            [4, 'Target', '', -1],
            [5, 'Scheduled', '', -1],
            [6, 'Unknown', '', -1]
        ]);
    }

    private function RegisterProfileInteger(string $name, string $icon, string $prefix, string $suffix, int $min, int $max, int $step): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        IPS_SetVariableProfileValues($name, $min, $max, $step);
    }

    private function RegisterProfileBooleanEx(string $name, string $icon, string $prefix, string $suffix, array $associations): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_BOOLEAN);
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        IPS_SetVariableProfileAssociations($name, []);

        foreach ($associations as $association) {
            IPS_SetVariableProfileAssociation(
                $name,
                (bool) $association[0],
                (string) $association[1],
                (string) $association[2],
                (int) $association[3]
            );
        }
    }

    private function RegisterProfileIntegerEx(string $name, string $icon, string $prefix, string $suffix, array $associations): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        IPS_SetVariableProfileAssociations($name, []);

        foreach ($associations as $association) {
            IPS_SetVariableProfileAssociation(
                $name,
                (int) $association[0],
                (string) $association[1],
                (string) $association[2],
                (int) $association[3]
            );
        }
    }

    private function ToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function PayloadToString(mixed $payload): string
    {
        if ($payload === null) {
            return '';
        }

        if (is_scalar($payload)) {
            $value = (string) $payload;
            if (strtolower($value) === 'null') {
                return '';
            }
            return trim($value, "\"");
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : $json;
    }

    private function IsNumericPayload(mixed $payload): bool
    {
        if (is_int($payload) || is_float($payload)) {
            return true;
        }

        if (is_string($payload)) {
            $test = trim($payload);
            if ($test === '' || strtolower($test) === 'null') {
                return false;
            }
            return is_numeric($test);
        }

        return false;
    }

    private function SetFloatIfNumeric(string $ident, mixed $payload): void
    {
        if ($this->IsNumericPayload($payload)) {
            $this->SetValue($ident, (float) $payload);
        }
    }

    private function MapChargeModeStringToInt(string $value): int
    {
        return match (strtolower(trim($value))) {
            'instant'            => 0,
            'pv'                 => 1,
            'eco'                => 2,
            'stop'               => 3,
            'target'             => 4,
            'scheduled',
            'scheduled_charging' => 5,
            default              => 6
        };
    }

    private function MapChargeModeIntToString(int $value): string
    {
        return match ($value) {
            0       => 'instant',
            1       => 'pv',
            2       => 'eco',
            3       => 'stop',
            4       => 'target',
            5       => 'scheduled_charging',
            default => 'instant'
        };
    }

    private function MapLimitTypeStringToInt(string $value): int
    {
        return match (strtolower(trim($value))) {
            'amount' => 1,
            'soc'    => 2,
            default  => 0
        };
    }

    private function MapLimitTypeIntToString(int $value): string
    {
        return match ($value) {
            1       => 'amount',
            2       => 'soc',
            default => 'none'
        };
    }
}