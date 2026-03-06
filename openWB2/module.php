<?php

class openWB2 extends IPSModuleStrict
{
    private const MQTT_SERVER_GUID = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    private const MQTT_CLIENT_SOCKET_GUID = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';

    public function Create(): void
    {
        parent::Create();

        $this->ConnectParent(self::MQTT_SERVER_GUID);

        $this->RegisterPropertyString('BaseTopic', 'openWB');
        $this->RegisterPropertyInteger('ChargePointID', 0);

        // Profile direkt im Modul erzeugen
        $this->RegisterProfiles();

        // Status / Read-Werte
        $this->RegisterVariableInteger('LPSoC', 'LP SoC', '~Intensity.100', 10);
        $this->RegisterVariableInteger('LPProSoC', 'LP Pro SoC', '~Intensity.100', 20);
        $this->RegisterVariableInteger('LPConfiguredCurrent', 'LP EVSE Current', 'OWB.Ampere', 30);
        $this->RegisterVariableFloat('LPPhaseCurrent1', 'LP Phase 1 Current', '~Ampere', 40);
        $this->RegisterVariableFloat('LPPhaseCurrent2', 'LP Phase 2 Current', '~Ampere', 50);
        $this->RegisterVariableFloat('LPPhaseCurrent3', 'LP Phase 3 Current', '~Ampere', 60);

        $this->RegisterVariableFloat('LPVoltage1', 'LP Phase 1 Voltage', '~Volt', 70);
        $this->RegisterVariableFloat('LPVoltage2', 'LP Phase 2 Voltage', '~Volt', 80);
        $this->RegisterVariableFloat('LPVoltage3', 'LP Phase 3 Voltage', '~Volt', 90);

        $this->RegisterVariableFloat('LPPower', 'LP Charging Power', '~Power', 100);
        $this->RegisterVariableInteger('LPPhasesInUse', 'LP Phases in Use', '', 110);

        $this->RegisterVariableBoolean('LPChargeState', 'LP Charge State', 'OWB.ChargeState', 120);
        $this->RegisterVariableBoolean('LPPlugState', 'LP Plug State', 'OWB.PlugState', 130);

        // simpleAPI liefert manual_lock, im Modul soll "Enabled" erhalten bleiben:
        // true = offen / false = gesperrt
        $this->RegisterVariableBoolean('LPChargePointEnabled', 'LP Chargepoint Enabled', 'OWB.ChargePointEnabled', 140);
        $this->EnableAction('LPChargePointEnabled');

        $this->RegisterVariableInteger('LPState', 'LP State', 'OWB.LPState', 150);

        $this->RegisterVariableInteger('LPFaultState', 'LP Fault State', '', 160);
        $this->RegisterVariableString('LPFaultString', 'LP Fault String', '', 170);
        $this->RegisterVariableString('LPStateString', 'LP State String', '', 180);
        $this->RegisterVariableString('LPVehicleName', 'LP Vehicle Name', '', 190);
        $this->RegisterVariableString('LPRFID', 'LP RFID', '', 200);

        $this->RegisterVariableFloat('LPDailyImported', 'LP Daily Imported', '~Electricity', 210);
        $this->RegisterVariableFloat('LPImported', 'LP Imported', '~Electricity', 220);

        // Schreibbare Parameter
        $this->RegisterVariableInteger('LPCurrent', 'LP Current', 'OWB.Ampere', 300);
        $this->EnableAction('LPCurrent');

        $this->RegisterVariableInteger('LPChargeMode', 'LP Charge Mode', 'OWB.ChargeMode', 310);
        $this->EnableAction('LPChargeMode');

        $this->RegisterVariableInteger('LPChargeLimitation', 'LP Charge Limitation', 'OWB.ChargeLimitation', 320);
        $this->EnableAction('LPChargeLimitation');

        $this->RegisterVariableInteger('LPSoCToChargeTo', 'LP SoC To Charge To', '~Intensity.100', 330);
        $this->EnableAction('LPSoCToChargeTo');

        $this->RegisterVariableInteger('LPEnergyToCharge', 'LP Energy To Charge', 'OWB.EnergyToCharge', 340);
        $this->EnableAction('LPEnergyToCharge');

        $this->RegisterVariableInteger('LPResetDirectCharge', 'LP Reset Direct Charge', 'OWB.ResetDirectCharge', 350);
        $this->EnableAction('LPResetDirectCharge');
    }

    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type'      => 'connect',
            'moduleIds' => ['{EE0D345A-CF31-428A-A613-33CE98E752DD}']
        ]);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->ConnectParent(self::MQTT_SERVER_GUID);

        $baseTopic = $this->ReadPropertyString('BaseTopic');
        if ($baseTopic === '') {
            $this->SetReceiveDataFilter('.*');
            return;
        }

        $filter = preg_quote($baseTopic . '/simpleAPI/', '/');
        $this->SetReceiveDataFilter('.*' . $filter . '.*');
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            return '';
        }

        if (!isset($data['Topic']) || !array_key_exists('Payload', $data)) {
            return '';
        }

        $topic = (string) $data['Topic'];
        $payload = $data['Payload'];

        $cpBase = $this->GetChargePointBaseTopic();
        if ($cpBase === '') {
            return '';
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
                // simpleAPI: true = gesperrt
                // Modul: true = offen
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
                // simpleAPI-Beispiel zeigt große importierte Werte; zur Anzeige als kWh -> /1000
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

            case $cpBase . '/charging_current':
                if ($this->IsNumericPayload($payload)) {
                    $this->SetValue('LPCurrent', (int) round((float) $payload));
                }
                break;
        }
    }

    public function RequestAction($Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'LPChargePointEnabled':
                // true = offen, false = sperren
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
                // Doku kennt keinen eigenen Reset-Topic.
                // Deshalb: Limit deaktivieren + Werte zurücksetzen.
                $this->PublishSetTopic('instant_charging_limit', 'none');
                $this->PublishSetTopic('instant_charging_limit_soc', '0');
                $this->PublishSetTopic('instant_charging_limit_amount', '1');

                $this->SetValue('LPChargeLimitation', 0);
                $this->SetValue('LPSoCToChargeTo', 0);
                $this->SetValue('LPEnergyToCharge', 1);
                $this->SetValue('LPResetDirectCharge', 1);
                break;

            default:
                throw new Exception('Invalid Ident');
        }
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

    private function PublishSetTopic(string $relativeTopic, string $payload, bool $retain = false): void
    {
        $baseTopic = rtrim($this->ReadPropertyString('BaseTopic'), '/');
        $fullTopic = $baseTopic . '/simpleAPI/set/' . ltrim($relativeTopic, '/');

        $data = [
            'DataID'            => self::MQTT_CLIENT_SOCKET_GUID,
            'PacketType'        => 3,
            'QualityOfService'  => 0,
            'Retain'            => $retain,
            'Topic'             => $fullTopic,
            'Payload'           => $payload
        ];

        $this->SendDataToParent(json_encode($data, JSON_UNESCAPED_SLASHES));
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

    private function ToBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function PayloadToString($payload): string
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

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function IsNumericPayload($payload): bool
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

    private function SetFloatIfNumeric(string $ident, $payload): void
    {
        if ($this->IsNumericPayload($payload)) {
            $this->SetValue($ident, (float) $payload);
        }
    }

    private function MapChargeModeStringToInt(string $value): int
    {
        $value = strtolower(trim($value));

        switch ($value) {
            case 'instant':
                return 0;
            case 'pv':
                return 1;
            case 'eco':
                return 2;
            case 'stop':
                return 3;
            case 'target':
                return 4;
            case 'scheduled_charging':
            case 'scheduled':
                return 5;
            default:
                return 6;
        }
    }

    private function MapChargeModeIntToString(int $value): string
    {
        switch ($value) {
            case 0:
                return 'instant';
            case 1:
                return 'pv';
            case 2:
                return 'eco';
            case 3:
                return 'stop';
            case 4:
                return 'target';
            case 5:
                return 'scheduled_charging';
            default:
                return 'instant';
        }
    }

    private function MapLimitTypeStringToInt(string $value): int
    {
        $value = strtolower(trim($value));

        switch ($value) {
            case 'amount':
                return 1;
            case 'soc':
                return 2;
            default:
                return 0;
        }
    }

    private function MapLimitTypeIntToString(int $value): string
    {
        switch ($value) {
            case 1:
                return 'amount';
            case 2:
                return 'soc';
            default:
                return 'none';
        }
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

    private function RegisterProfileFloat(string $name, string $icon, string $prefix, string $suffix, float $min, float $max, float $step, int $digits): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_FLOAT);
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        IPS_SetVariableProfileValues($name, $min, $max, $step);
        IPS_SetVariableProfileDigits($name, $digits);
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
}