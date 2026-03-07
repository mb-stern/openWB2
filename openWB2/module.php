<?php

class openWB2 extends IPSModuleStrict
{
    private const MQTT_SERVER_GUID = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    private const MQTT_CLIENT_SOCKET_GUID = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';

    public function Create(): void
    {
        parent::Create();

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
        $json = json_encode([
            'type'      => 'connect',
            'moduleIDs' => [
                // MQTT-Client
                '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}'
            ]
        ]);

        return ($json !== false) ? $json : '[]';
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $baseTopic = $this->ReadPropertyString('BaseTopic');
        if ($baseTopic === '') {
            $this->SetReceiveDataFilter('.*');
            return;
        }

        $filter = preg_quote($baseTopic . '/simpleAPI/', '/');
        $this->SetReceiveDataFilter('.*' . $filter . '.*');
    }

    public function GetConfigurationForm(): string
    {
        $form = [
            'elements' => [
                [
                    'name'    => 'BaseTopic',
                    'type'    => 'ValidationTextBox',
                    'caption' => 'MQTT Topic'
                ],
                [
                    'name'    => 'ChargePointID',
                    'type'    => 'NumberSpinner',
                    'caption' => 'Charging Point'
                ]
            ],
            'actions' => [
                [
                    'type' => 'TestCenter'
                ]
            ]
        ];

        return json_encode($form);
    }
   
    public function ReceiveData(string $JSONString): string
    {
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);

        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            $this->SendDebug('ReceiveData', 'Ungültiges JSON', 0);
            return '';
        }

        if (!isset($data['Topic']) || !array_key_exists('Payload', $data)) {
            $this->SendDebug('ReceiveData', 'Topic oder Payload fehlt', 0);
            $this->SendDebug('ReceiveData Data', json_encode($data), 0);
            return '';
        }

        $topic = (string) $data['Topic'];
        $payload = $data['Payload'];

        if (is_string($payload)) {
            $decodedPayload = @hex2bin($payload);
            if ($decodedPayload !== false) {
                $payload = $decodedPayload;
            }
        }

        if (is_string($payload)) {
            $payload = trim($payload);
        }

        $this->SendDebug('Topic', $topic, 0);
        $this->SendDebug('Payload', is_scalar($payload) || $payload === null ? (string) $payload : json_encode($payload), 0);

        $cpBases = $this->GetChargePointBaseTopics();
        if ($cpBases === []) {
            $this->SendDebug('ReceiveData', 'Keine ChargePoint-Basen ermittelt', 0);
            return '';
        }

        foreach ($cpBases as $cpBase) {
            $this->SendDebug('Prüfe Base', $cpBase, 0);

            switch ($topic) {
                case $cpBase . '/soc/soc':
                    $this->SendDebug('Match', 'soc/soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPSoC', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPSoC = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/pro_soc':
                    $this->SendDebug('Match', 'pro_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPProSoC', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPProSoC = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/evse_current':
                    $this->SendDebug('Match', 'evse_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPConfiguredCurrent', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPConfiguredCurrent = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/currents/1':
                    $this->SendDebug('Match', 'currents/1', 0);
                    $this->SetFloatIfNumeric('LPPhaseCurrent1', $payload);
                    return '';

                case $cpBase . '/currents/2':
                    $this->SendDebug('Match', 'currents/2', 0);
                    $this->SetFloatIfNumeric('LPPhaseCurrent2', $payload);
                    return '';

                case $cpBase . '/currents/3':
                    $this->SendDebug('Match', 'currents/3', 0);
                    $this->SetFloatIfNumeric('LPPhaseCurrent3', $payload);
                    return '';

                case $cpBase . '/voltages/1':
                    $this->SendDebug('Match', 'voltages/1', 0);
                    $this->SetFloatIfNumeric('LPVoltage1', $payload);
                    return '';

                case $cpBase . '/voltages/2':
                    $this->SendDebug('Match', 'voltages/2', 0);
                    $this->SetFloatIfNumeric('LPVoltage2', $payload);
                    return '';

                case $cpBase . '/voltages/3':
                    $this->SendDebug('Match', 'voltages/3', 0);
                    $this->SetFloatIfNumeric('LPVoltage3', $payload);
                    return '';

                case $cpBase . '/power':
                    $this->SendDebug('Match', 'power', 0);
                    $this->SetFloatIfNumeric('LPPower', $payload);
                    return '';

                case $cpBase . '/phases_in_use':
                    $this->SendDebug('Match', 'phases_in_use', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPPhasesInUse', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPPhasesInUse = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/charge_state':
                    $this->SendDebug('Match', 'charge_state', 0);
                    $value = $this->ToBool($payload);
                    $this->SetValue('LPChargeState', $value);
                    $this->SendDebug('SetValue', 'LPChargeState = ' . ($value ? 'true' : 'false'), 0);
                    $this->UpdateLPState();
                    return '';

                case $cpBase . '/plug_state':
                    $this->SendDebug('Match', 'plug_state', 0);
                    $value = $this->ToBool($payload);
                    $this->SetValue('LPPlugState', $value);
                    $this->SendDebug('SetValue', 'LPPlugState = ' . ($value ? 'true' : 'false'), 0);
                    $this->UpdateLPState();
                    return '';

                case $cpBase . '/manual_lock':
                    $this->SendDebug('Match', 'manual_lock', 0);
                    $value = !$this->ToBool($payload);
                    $this->SetValue('LPChargePointEnabled', $value);
                    $this->SendDebug('SetValue', 'LPChargePointEnabled = ' . ($value ? 'true' : 'false'), 0);
                    return '';

                case $cpBase . '/fault_state':
                    $this->SendDebug('Match', 'fault_state', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPFaultState', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPFaultState = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/fault_str':
                    $this->SendDebug('Match', 'fault_str', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('LPFaultString', $value);
                    $this->SendDebug('SetValue', 'LPFaultString = ' . $value, 0);
                    return '';

                case $cpBase . '/state_str':
                    $this->SendDebug('Match', 'state_str', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('LPStateString', $value);
                    $this->SendDebug('SetValue', 'LPStateString = ' . $value, 0);
                    return '';

                case $cpBase . '/vehicle_name':
                    $this->SendDebug('Match', 'vehicle_name', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('LPVehicleName', $value);
                    $this->SendDebug('SetValue', 'LPVehicleName = ' . $value, 0);
                    return '';

                case $cpBase . '/rfid':
                    $this->SendDebug('Match', 'rfid', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('LPRFID', $value);
                    $this->SendDebug('SetValue', 'LPRFID = ' . $value, 0);
                    return '';

                case $cpBase . '/daily_imported':
                    $this->SendDebug('Match', 'daily_imported', 0);
                    $this->SetFloatIfNumeric('LPDailyImported', $payload);
                    return '';

                case $cpBase . '/imported':
                    $this->SendDebug('Match', 'imported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValue('LPImported', $value);
                        $this->SendDebug('SetValue', 'LPImported = ' . $value, 0);
                    }
                    return '';

                case $cpBase . '/chargemode':
                    $this->SendDebug('Match', 'chargemode', 0);
                    $value = $this->MapChargeModeStringToInt($this->PayloadToString($payload));
                    $this->SetValue('LPChargeMode', $value);
                    $this->SendDebug('SetValue', 'LPChargeMode = ' . $value, 0);
                    return '';

                case $cpBase . '/instant_charging_limit':
                    $this->SendDebug('Match', 'instant_charging_limit', 0);
                    $value = $this->MapLimitTypeStringToInt($this->PayloadToString($payload));
                    $this->SetValue('LPChargeLimitation', $value);
                    $this->SendDebug('SetValue', 'LPChargeLimitation = ' . $value, 0);
                    return '';

                case $cpBase . '/instant_charging_limit_soc':
                    $this->SendDebug('Match', 'instant_charging_limit_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPSoCToChargeTo', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPSoCToChargeTo = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/instant_charging_limit_amount':
                    $this->SendDebug('Match', 'instant_charging_limit_amount', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPEnergyToCharge', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPEnergyToCharge = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/charging_current':
                    $this->SendDebug('Match', 'charging_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('LPCurrent', (int) round((float) $payload));
                        $this->SendDebug('SetValue', 'LPCurrent = ' . (int) round((float) $payload), 0);
                    }
                    return '';
            }
        }

        $this->SendDebug('Kein Match', $topic, 0);
        return '';
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
            'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType'       => 3,
            'QualityOfService' => 0,
            'Retain'           => $retain,
            'Topic'            => $fullTopic,
            'Payload'          => strtoupper(bin2hex($payload))
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        $this->SendDebug('Publish Topic', $fullTopic, 0);
        $this->SendDebug('Publish Payload Text', $payload, 0);
        $this->SendDebug('Publish Payload HEX', strtoupper(bin2hex($payload)), 0);
        $this->SendDebug('Publish JSON', $json, 0);

        $result = $this->SendDataToParent($json);
        $this->SendDebug('Publish Result', (string) $result, 0);
    }

    private function GetChargePointBaseTopics(): array
    {
        $baseTopic = trim($this->ReadPropertyString('BaseTopic'));
        $chargePointID = $this->ReadPropertyInteger('ChargePointID');

        if ($baseTopic === '') {
            $this->SendDebug('GetChargePointBaseTopics', 'BaseTopic ist leer', 0);
            return [];
        }

        $base = rtrim($baseTopic, '/') . '/simpleAPI/chargepoint';

        $topics = [
            $base . '/' . $chargePointID
        ];

        // Nur für Ladepunkt 0 zusätzlich die Kurzform ohne ID erlauben
        if ($chargePointID === 0) {
            $topics[] = $base;
        }

        $this->SendDebug('GetChargePointBaseTopics', json_encode($topics), 0);

        return $topics;
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
        if (!$this->IsNumericPayload($payload)) {
            $this->SendDebug('SetFloatIfNumeric', $ident . ' Payload nicht numerisch: ' . (string)$payload, 0);
            return;
        }

        $value = (float) $payload;

        if (is_nan($value) || is_infinite($value)) {
            $this->SendDebug('SetFloatIfNumeric', $ident . ' ungültiger Wert: ' . (string)$payload, 0);
            return;
        }

        $this->SetValue($ident, $value);
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