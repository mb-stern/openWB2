<?php

class openWB2 extends IPSModuleStrict
{
   public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('BaseTopic', 'openWB');
        $this->RegisterPropertyInteger('ChargePointID', 0);

        // Profile direkt im Modul erzeugen
        $this->RegisterProfiles();

        // Status / Read-Werte
        $this->RegisterVariableInteger('SoC', 'SoC', '~Intensity.100', 10);
        $this->RegisterVariableInteger('ProSoC', 'Pro SoC', '~Intensity.100', 20);
        $this->RegisterVariableInteger('ConfiguredCurrent', 'EVSE Aktuell', 'OWB.Ampere', 30);
        $this->RegisterVariableFloat('PhaseCurrent1', 'Phase 1 Aktuell', '~Ampere', 40);
        $this->RegisterVariableFloat('PhaseCurrent2', 'Phase 2 Aktuell', '~Ampere', 50);
        $this->RegisterVariableFloat('PhaseCurrent3', 'Phase 3 Aktuell', '~Ampere', 60);
        $this->RegisterVariableFloat('Voltage1', 'Phase 1 Spannung', '~Volt', 70);
        $this->RegisterVariableFloat('Voltage2', 'Phase 2 Spannung', '~Volt', 80);
        $this->RegisterVariableFloat('Voltage3', 'Phase 3 Spannung', '~Volt', 90);
        $this->RegisterVariableFloat('Power', 'Ladeleistung', '~Watt', 100);
        $this->RegisterVariableInteger('PhasesInUse', 'Verwendete Phasen', '', 110);
        $this->RegisterVariableBoolean('ChargeState', 'Ladestatus', 'OWB.ChargeState', 120);
        $this->RegisterVariableBoolean('PlugState', 'Stecker Status', 'OWB.PlugState', 130);
        $this->RegisterVariableBoolean('ChargePointEnabled', 'Ladepunkt sperren', 'OWB.ChargePointEnabled', 140);
        $this->RegisterVariableInteger('State', 'Status', 'OWB.LPState', 150);
        $this->RegisterVariableInteger('FaultState', 'Fehlerstatus', '', 160);
        $this->RegisterVariableString('FaultString', 'Fehlertext', '', 170);
        $this->RegisterVariableString('StateString', 'Statustext', '', 180);
        $this->RegisterVariableString('VehicleName', 'Fahrzeug Name', '', 190);
        $this->RegisterVariableString('RFID', 'RFID', '', 200);
        $this->RegisterVariableFloat('DailyImported', 'Energie Tag', '~Electricity', 210);
        $this->RegisterVariableFloat('Imported', 'Energie Gesamt', '~Electricity', 220);

        // Schreibbare Parameter gemäß simpleAPI Set-Topics

        $this->RegisterVariableBoolean('SetChargePointLock', 'Ladepunkt sperren', 'OWB.ChargePointEnabled', 290);
        $this->EnableAction('SetChargePointLock');

        $this->RegisterVariableInteger('SetChargeMode', 'Lademodus', 'OWB.ChargeMode', 300);
        $this->EnableAction('SetChargeMode');

        $this->RegisterVariableInteger('SetChargeCurrent', 'Stromstärke', 'OWB.Ampere', 310);
        $this->EnableAction('SetChargeCurrent');

        $this->RegisterVariableInteger('SetMinimalPvSoc', 'Mindes-SoC für das Fahrzeug', '~Intensity.100', 320);
        $this->EnableAction('SetMinimalPvSoc');

        $this->RegisterVariableInteger('SetMinimalPermanentCurrent', 'Minimaler Dauerstrom', 'OWB.Ampere', 330);
        $this->EnableAction('SetMinimalPermanentCurrent');

        $this->RegisterVariableFloat('SetMaxPriceEco', 'Höchstpreis Eco', 'OWB.Price', 340);
        $this->EnableAction('SetMaxPriceEco');

        $this->RegisterVariableInteger('SetBatMode', 'Ladepriorität', 'OWB.BatMode', 360);
        $this->EnableAction('SetBatMode');

        $this->RegisterVariableInteger('SetInstantChargingLimit', 'Begrenzung', 'OWB.ChargeLimitation', 370);
        $this->EnableAction('SetInstantChargingLimit');

        $this->RegisterVariableInteger('SetInstantChargingLimitSoc', 'SoC-Limit für das Fahrzeug', '~Intensity.100', 380);
        $this->EnableAction('SetInstantChargingLimitSoc');

        $this->RegisterVariableInteger('SetInstantChargingLimitAmount', 'Energie Limit', 'OWB.EnergyToCharge', 390);
        $this->EnableAction('SetInstantChargingLimitAmount');
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
        //$this->SendDebug('ReceiveData JSON', $JSONString, 0);

        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            //$this->SendDebug('ReceiveData', 'Ungültiges JSON', 0);
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

        //$this->SendDebug('Topic', $topic, 0);
        //$this->SendDebug('Payload', is_scalar($payload) || $payload === null ? (string) $payload : json_encode($payload), 0);

        $cpBases = $this->GetChargePointBaseTopics();
        if ($cpBases === []) {
            $this->SendDebug('ReceiveData', 'Keine ChargePoint-Basen ermittelt', 0);
            return '';
        }

        foreach ($cpBases as $cpBase) {
            //$this->SendDebug('Prüfe Base', $cpBase, 0);

            switch ($topic) {
                case $cpBase . '/soc/soc':
                    //$this->SendDebug('Match', 'soc/soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('SoC', (int) round((float) $payload));
                        //$this->SendDebug('SetValue', 'LPSoC = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/pro_soc':
                    //$this->SendDebug('Match', 'pro_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('ProSoC', (int) round((float) $payload));
                        //$this->SendDebug('SetValue', 'LPProSoC = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/evse_current':
                    //$this->SendDebug('Match', 'evse_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('ConfiguredCurrent', (int) round((float) $payload));
                        //$this->SendDebug('SetValue', 'LPConfiguredCurrent = ' . (int) round((float) $payload), 0);
                    }
                    return '';

                case $cpBase . '/currents/1':
                    //$this->SendDebug('Match', 'currents/1', 0);
                    $this->SetFloatIfNumeric('PhaseCurrent1', $payload);
                    return '';

                case $cpBase . '/currents/2':
                    //$this->SendDebug('Match', 'currents/2', 0);
                    $this->SetFloatIfNumeric('PhaseCurrent2', $payload);
                    return '';

                case $cpBase . '/currents/3':
                    //$this->SendDebug('Match', 'currents/3', 0);
                    $this->SetFloatIfNumeric('PhaseCurrent3', $payload);
                    return '';

                case $cpBase . '/voltages/1':
                    //$this->SendDebug('Match', 'voltages/1', 0);
                    $this->SetFloatIfNumeric('Voltage1', $payload);
                    return '';

                case $cpBase . '/voltages/2':
                    //$this->SendDebug('Match', 'voltages/2', 0);
                    $this->SetFloatIfNumeric('Voltage2', $payload);
                    return '';

                case $cpBase . '/voltages/3':
                    //$this->SendDebug('Match', 'voltages/3', 0);
                    $this->SetFloatIfNumeric('Voltage3', $payload);
                    return '';

                case $cpBase . '/power':
                    //$this->SendDebug('Match', 'power', 0);
                    $this->SetFloatIfNumeric('Power', $payload);
                    return '';

                case $cpBase . '/phases_in_use':
                    //$this->SendDebug('Match', 'phases_in_use', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('PhasesInUse', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/charge_state':
                    //$this->SendDebug('Match', 'charge_state', 0);
                    $value = $this->ToBool($payload);
                    $this->SetValue('ChargeState', $value);
        
                    $this->UpdateLPState();
                    return '';

                case $cpBase . '/plug_state':
                    //$this->SendDebug('Match', 'plug_state', 0);
                    $value = $this->ToBool($payload);
                    $this->SetValue('PlugState', $value);
                    $this->UpdateLPState();
                    return '';

                case $cpBase . '/manual_lock':
                    //$this->SendDebug('Match', 'manual_lock', 0);
                    $isLocked = $this->ToBool($payload);
                    $this->SetValue('SetChargePointLock', !$isLocked);
                    return '';

                case $cpBase . '/fault_state':
                    //$this->SendDebug('Match', 'fault_state', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('FaultState', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/fault_str':
                    //$this->SendDebug('Match', 'fault_str', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('FaultString', $value);
                    return '';

                case $cpBase . '/state_str':
                    //$this->SendDebug('Match', 'state_str', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('StateString', $value);
                    return '';

                case $cpBase . '/vehicle_name':
                    //$this->SendDebug('Match', 'vehicle_name', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('VehicleName', $value);
                    return '';

                case $cpBase . '/rfid':
                    //$this->SendDebug('Match', 'rfid', 0);
                    $value = $this->PayloadToString($payload);
                    $this->SetValue('RFID', $value);
                    return '';

                case $cpBase . '/daily_imported':
                    //$this->SendDebug('Match', 'daily_imported', 0);
                    $value = ((float) $payload) / 1000;
                    $this->SetValue('DailyImported', $value);
                    return '';

                case $cpBase . '/imported':
                    //$this->SendDebug('Match', 'imported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValue('Imported', $value);
                    }
                    return '';

                case $cpBase . '/chargemode':
                    //$this->SendDebug('Match', 'chargemode', 0);
                    $value = $this->MapChargeModeStringToInt($this->PayloadToString($payload));
                    $this->SetValue('SetChargeMode', $value);
                    return '';

                case $cpBase . '/instant_charging_limit':
                    //$this->SendDebug('Match', 'instant_charging_limit', 0);
                    $value = $this->MapLimitTypeStringToInt($this->PayloadToString($payload));
                    $this->SetValue('SetInstantChargingLimit', $value);
                    return '';

                case $cpBase . '/instant_charging_limit_soc':
                    //$this->SendDebug('Match', 'instant_charging_limit_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValue('SetInstantChargingLimitSoc', $value);
                    }
                    return '';

                case $cpBase . '/instant_charging_limit_amount':
                    //$this->SendDebug('Match', 'instant_charging_limit_amount', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValue('SetInstantChargingLimitAmount', $value);
                    }
                    return '';

                case $cpBase . '/charging_current':
                    //$this->SendDebug('Match', 'charging_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValue('SetChargeCurrent', $value);
                    }
                    return '';

                case $cpBase . '/minimal_pv_soc':
                    //$this->SendDebug('Match', 'minimal_pv_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValue('SetMinimalPvSoc', $value);
                    }
                    return '';

                case $cpBase . '/minimal_permanent_current':
                    //$this->SendDebug('Match', 'minimal_permanent_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValue('SetMinimalPermanentCurrent', $value);
                    }
                    return '';

                case $cpBase . '/max_price_eco':
                    //$this->SendDebug('Match', 'max_price_eco', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (float) $payload;
                        if (!is_nan($value) && !is_infinite($value)) {
                            $this->SetValue('SetMaxPriceEco', $value);
                        }
                    }
                    return '';

                case rtrim($this->ReadPropertyString('BaseTopic'), '/') . '/simpleAPI/bat_mode':
                    //$this->SendDebug('Match', 'bat_mode', 0);
                    $value = $this->MapBatModeStringToInt($this->PayloadToString($payload));
                    $this->SetValue('SetBatMode', $value);
                    return '';
            }
        }

        //$this->SendDebug('Kein Match', $topic, 0);
        return '';
    }

    public function RequestAction($Ident, mixed $Value): void
    {
        $this->SendDebug('RequestAction', $Ident . ' = ' . var_export($Value, true), 0);

        $cpSetBase = $this->GetChargePointSetBaseTopic();

        switch ($Ident) {
            case 'SetChargeMode':
                $modeString = $this->MapChargeModeIntToString((int) $Value);
                $this->PublishSetTopic($cpSetBase . '/chargemode', $modeString);
                $this->SetValue('SetChargeMode', (int) $Value);
                break;

            case 'SetChargeCurrent':
                $current = max(6, min(32, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/chargecurrent', (string) $current);
                $this->SetValue('SetChargeCurrent', $current);
                break;

            case 'SetMinimalPvSoc':
                $soc = max(0, min(100, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/minimal_pv_soc', (string) $soc);
                $this->SetValue('SetMinimalPvSoc', $soc);
                break;

            case 'SetMinimalPermanentCurrent':
                $current = max(6, min(32, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/minimal_permanent_current', (string) $current);
                $this->SetValue('SetMinimalPermanentCurrent', $current);
                break;

            case 'SetMaxPriceEco':
                $price = max(0, (float) $Value);
                $payload = number_format($price, 2, '.', '');
                $this->PublishSetTopic($cpSetBase . '/max_price_eco', $payload);
                $this->SetValue('SetMaxPriceEco', $price);
                break;

            case 'SetChargePointLock':
                $enabled = (bool)$Value;
                $payload = $enabled ? 'false' : 'true';
                $this->PublishSetTopic($cpSetBase . '/chargepoint_lock', $payload);
                $this->SetValue('SetChargePointLock', $enabled);
                break;

            case 'SetBatMode':
                $batMode = $this->MapBatModeIntToString((int) $Value);
                $this->PublishSetTopic('bat_mode', $batMode);
                $this->SetValue('SetBatMode', (int) $Value);
                break;

            case 'SetInstantChargingLimit':
                $limitType = $this->MapLimitTypeIntToString((int) $Value);
                $this->PublishSetTopic($cpSetBase . '/instant_charging_limit', $limitType);
                $this->SetValue('SetInstantChargingLimit', (int) $Value);
                break;

            case 'SetInstantChargingLimitSoc':
                $soc = max(0, min(100, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/instant_charging_limit_soc', (string) $soc);
                $this->SetValue('SetInstantChargingLimitSoc', $soc);
                break;

            case 'SetInstantChargingLimitAmount':
                $energy = max(1, min(50, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/instant_charging_limit_amount', (string) $energy);
                $this->SetValue('SetInstantChargingLimitAmount', $energy);
                break;

            default:
                throw new Exception('Invalid Ident');
        }
    }

    private function GetChargePointSetBaseTopic(): string
    {
        $chargePointID = $this->ReadPropertyInteger('ChargePointID');
        return 'chargepoint/' . $chargePointID;
    }

    private function RegisterProfiles(): void
    {
        $this->RegisterProfileIntegerEx('OWB.ChargeLimitation', 'Power', '', '', [
            [0, 'Aus', '', -1],
            [1, 'Energie', '', -1],
            [2, 'EV-SoC', '', -1]
        ]);

        $this->RegisterProfileIntegerEx('OWB.ResetDirectCharge', 'Power', '', '', [
            [1, 'Reset', '', -1]
        ]);

        $this->RegisterProfileInteger('OWB.Ampere', 'Electricity', '', ' A', 0, 32, 1);

        $this->RegisterProfileInteger('OWB.EnergyToCharge', 'Electricity', '', ' kWh', 1, 50, 1);

        $this->RegisterProfileBooleanEx('OWB.PlugState', 'Car', '', '', [
            [false, 'Frei', '', 0xFF0000],
            [true, 'Gesteckt', '', 0x00FF00]
        ]);

        $this->RegisterProfileBooleanEx('OWB.ChargeState', 'Car', '', '', [
            [false, 'Aus', '', 0xFF0000],
            [true, 'Laden', '', 0x00FF00]
        ]);

        $this->RegisterProfileBooleanEx('OWB.ChargePointEnabled', 'Car', '', '', [
            [false, 'Nein', '', 0x00FF00],
            [true, 'Ja', '', 0xFF0000]
        ]);

        $this->RegisterProfileIntegerEx('OWB.LPState', 'Information', '', '', [
            [0, 'Frei', '', 0x00FF00],
            [1, 'Blockiert', '', 0xFFFF00],
            [2, 'Laden', '', 0xFF0000]
        ]);

        $this->RegisterProfileIntegerEx('OWB.ChargeMode', 'Car', '', '', [
            [0, 'Sofort', '', -1],
            [1, 'PV', '', -1],
            [2, 'Eco', '', -1],
            [3, 'Stop', '', -1],
            [4, 'Ziel', '', -1]
        ]);

        $this->RegisterProfileFloat('OWB.Price', 'Money', '', ' CHF/kWh', 0, 10, 0.01, 2);

        $this->RegisterProfileIntegerEx('OWB.BatMode', 'Battery', '', '', [
            [0, 'Nach SoC des Speichers', '', -1],
            [1, 'Fahrzeug', '', -1],
            [2, 'Speicher', '', -1]
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
            'Payload'          => bin2hex($payload)
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        $this->SendDebug('Publish Topic', $fullTopic, 0);
        $this->SendDebug('Publish Payload', $payload, 0);
        //$this->SendDebug('Publish JSON', $json, 0);

        $result = $this->SendDataToParent($json);
        $this->SendDebug('Publish Result', (string)$result, 0);
    }

    private function GetChargePointBaseTopics(): array
    {
        $baseTopic = trim($this->ReadPropertyString('BaseTopic'));
        $chargePointID = $this->ReadPropertyInteger('ChargePointID');

        if ($baseTopic === '') {
            //$this->SendDebug('GetChargePointBaseTopics', 'BaseTopic ist leer', 0);
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

        //$this->SendDebug('GetChargePointBaseTopics', json_encode($topics), 0);

        return $topics;
    }

    private function UpdateLPState(): void
    {
        $plugged = $this->GetValue('PlugState');
        $charging = $this->GetValue('ChargeState');

        if (!$plugged) {
            $this->SetValue('State', 0);
            return;
        }

        if ($charging) {
            $this->SetValue('State', 2);
            return;
        }

        $this->SetValue('State', 1);
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
            case 'instant_charging':
                return 0;

            case 'pv':
            case 'pv_charging':
                return 1;

            case 'eco':
            case 'eco_charging':
                return 2;

            case 'stop':
                return 3;

            case 'target':
                return 4;

            default:
                return 0;
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

    private function MapBatModeIntToString(int $value): string
    {
        switch ($value) {
            case 0:
                return 'min_soc_bat_mode';
            case 1:
                return 'ev_mode';
            case 2:
                return 'bat_mode';
            default:
                return 'ev_mode';
        }
    }

    private function MapBatModeStringToInt(string $value): int
    {
        $value = strtolower(trim($value));

        switch ($value) {
            case 'min_soc_bat_mode':
                return 0;
            case 'ev_mode':
                return 1;
            case 'bat_mode':
                return 2;
            default:
                return 1;
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