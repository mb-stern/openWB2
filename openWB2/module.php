<?php

class openWB2 extends IPSModuleStrict
{
   public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('BaseTopic', 'openWB');
        $this->RegisterPropertyInteger('ChargePointID', 0);
        $this->RegisterPropertyInteger('ChargeTemplateID', 0);
        $this->RegisterPropertyInteger('MinCurrentPerPhase', 6);
        $this->RegisterPropertyInteger('MaxCurrentPerPhase', 16);
        $this->RegisterPropertyInteger('PhaseSwitchLockTime', 60);
        $this->RegisterPropertyString('SelectedVariables', '[]');

        // Profile erzeugen
        $this->RegisterProfiles();

        // Schreibbare Parameter gemäß simpleAPI Set-Topics

        $this->RegisterVariableBoolean('SetChargePointLock', 'Ladepunkt sperren', 'OWB.ChargePointEnabled', 290);
        $this->EnableAction('SetChargePointLock');

        $this->RegisterVariableInteger('SetChargeMode', 'Lademodus', 'OWB.ChargeMode', 300);
        $this->EnableAction('SetChargeMode');

        $this->RegisterVariableInteger('SetChargeCurrent', 'Stromstärke', '', 310);
        $this->EnableAction('SetChargeCurrent');

        $this->RegisterVariableInteger('SetChargePower', 'Sollleistung', '', 312);
        $this->EnableAction('SetChargePower');

        $this->RegisterVariableInteger('SetMinimalPvSoc', 'Mindes-SoC für das Fahrzeug', '~Intensity.100', 320);
        $this->EnableAction('SetMinimalPvSoc');

        $this->RegisterVariableInteger('SetMinimalPermanentCurrent', 'Minimaler Dauerstrom', '', 330);
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

        // Phasenumschaltung

        $this->RegisterVariableInteger('PhasesToUse', 'Phasen Sofortladen', 'OWB.PhasesToUse', 315);
        $this->EnableAction('PhasesToUse');

        $this->RegisterTimer('PhaseSwitchLockTimer', 0, 'OWB_ClearPhaseSwitchLock($_IPS["TARGET"]);');
        $this->SetBuffer('PhaseSwitchLock', '0');

        $this->RegisterAttributeString('ChargeTemplateJSON', '');

        $this->SetBuffer('PendingPhasesToUse', '0');

        $this->RegisterTimer('ApplyChargeCurrentTimer', 0, 'OWB_ApplyPendingChargeCurrent($_IPS["TARGET"]);');
        $this->SetBuffer('PendingChargeCurrent', '0');
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

        $baseTopic = trim($this->ReadPropertyString('BaseTopic'));
        if ($baseTopic === '') {
            $this->SetReceiveDataFilter('.*');
        } else {
            $baseTopic = rtrim($baseTopic, '/');
            $filter = '.*"Topic":"' . preg_quote($baseTopic, '/') . '\/.*';
            $this->SetReceiveDataFilter($filter);
        }

        $savedTemplate = $this->ReadAttributeString('ChargeTemplateJSON');
        if ($savedTemplate !== '') {
            $this->SetBuffer('ChargeTemplateJSON', $savedTemplate);
        }

        $this->SyncSelectedVariables();
        $this->UpdateDynamicProfiles();
    }

    public function GetConfigurationForm(): string
    {
        $elements = [
            [
                'name'    => 'BaseTopic',
                'type'    => 'ValidationTextBox',
                'caption' => 'MQTT Topic'
            ],
            [
                'name'    => 'ChargePointID',
                'type'    => 'NumberSpinner',
                'caption' => 'Ladepunkt ID'
            ],
            [
                'name'    => 'ChargeTemplateID',
                'type'    => 'NumberSpinner',
                'caption' => 'Ladepunkt-Profil ID'
            ],
            [
                'name'    => 'MinCurrentPerPhase',
                'type'    => 'NumberSpinner',
                'caption' => 'Minimalstrom pro Phase (A)'
            ],
            [
                'name'    => 'MaxCurrentPerPhase',
                'type'    => 'NumberSpinner',
                'caption' => 'Maximalstrom pro Phase (A)'
            ],
            [
                'name'    => 'PhaseSwitchLockTime',
                'type'    => 'NumberSpinner',
                'caption' => 'Sperrzeit Phasenumschaltung (Sekunden)'
            ]
        ];

        foreach ($this->GetAvailableVariables() as $groupName => $variables) {
            $options = [];

            foreach ($variables as $var) {
                $options[] = [
                    'caption' => $var['name'],
                    'value'   => $var['ident']
                ];
            }

            $elements[] = [
                'type'    => 'GroupBox',
                'caption' => $groupName,
                'items'   => [
                    [
                        'name'    => 'SelectedVariables',
                        'type'    => 'CheckBoxList',
                        'caption' => 'Variablen',
                        'rowCount' => max(4, count($options)),
                        'add'     => false,
                        'delete'  => false,
                        'sort'    => false,
                        'values'  => $options
                    ]
                ]
            ];
        }

        $form = [
            'elements' => $elements,
            'actions'  => [
                [
                    'type'    => 'Label',
                    'caption' => 'Sag danke und unterstütze den Modulentwickler:'
                ],
                [
                    'type' => 'RowLayout',
                    'items' => [
                        [
                            'type' => 'Image',
                            'onClick' => "echo 'https://paypal.me/mbstern';",
                            'image' => 'data:image/jpeg;base64,...'
                        ],
                        [
                            'type' => 'Label',
                            'caption' => ''
                        ]
                    ]
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

        $baseTopic = rtrim($this->ReadPropertyString('BaseTopic'), '/');
        $templateId = (int) $this->ReadPropertyInteger('ChargeTemplateID');

            if ($topic === $baseTopic . '/vehicle/template/charge_template/' . $templateId) {
            $value = trim((string) $payload);
            $this->SetBuffer('ChargeTemplateJSON', $value);
            $this->WriteAttributeString('ChargeTemplateJSON', $value);

            $this->SendDebug('ChargeTemplate', $value, 0);

            $template = json_decode($value, true);
            if (is_array($template)
                && isset($template['chargemode']['instant_charging']['phases_to_use'])
                && in_array((int)$template['chargemode']['instant_charging']['phases_to_use'], [1, 3], true)
            ) {
                $receivedPhases = (int) $template['chargemode']['instant_charging']['phases_to_use'];
                $pendingPhases  = (int) $this->GetBuffer('PendingPhasesToUse');

                // Wenn gerade keine Umstellung offen ist, empfangenen Wert normal übernehmen
                if ($pendingPhases === 0) {
                    $this->SetValue('PhasesToUse', $receivedPhases);
                } else {
                    // Wenn openWB den gewünschten Wert zurückmeldet, übernehmen und Pending löschen
                    if ($receivedPhases === $pendingPhases) {
                        $this->SetValue('PhasesToUse', $receivedPhases);
                        $this->SetBuffer('PendingPhasesToUse', '0');
                        $this->SendDebug('ChargeTemplate', 'Pending-Phasenumschaltung bestätigt: ' . $receivedPhases, 0);
                    } else {
                        // Altes Template empfangen -> nur puffern, aber UI/Sollwert nicht zurücksetzen
                        $this->SendDebug(
                            'ChargeTemplate',
                            'Altes Template empfangen (' . $receivedPhases . '), Pending bleibt auf ' . $pendingPhases,
                            0
                        );
                    }
                }
            }

            return '';
        }

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
                    }
                    return '';

                case $cpBase . '/pro_soc':
                    //$this->SendDebug('Match', 'pro_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('ProSoC', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/evse_current':
                    //$this->SendDebug('Match', 'evse_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('ConfiguredCurrent', (int) round((float) $payload));
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

                case $cpBase . '/fault_state':
                    //$this->SendDebug('Match', 'fault_state', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('FaultState', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/fault_str':
                    //$this->SendDebug('Match', 'fault_str', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValue('FaultString', $value);
                    return '';

                case $cpBase . '/state_str':
                    //$this->SendDebug('Match', 'state_str', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValue('StateString', $value);
                    return '';

                case $cpBase . '/vehicle_name':
                    //$this->SendDebug('Match', 'vehicle_name', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValue('VehicleName', $value);
                    return '';

                case $cpBase . '/rfid':
                    //$this->SendDebug('Match', 'rfid', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
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

                case $cpBase . '/rfid_timestamp':
                    //$this->SendDebug('Match', 'rfid_timestamp', 0);
                    $this->SetValue('RFIDTimestamp', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/daily_exported':
                    //$this->SendDebug('Match', 'daily_exported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValue('DailyExported', $value);
                    }
                    return '';

                case $cpBase . '/exported':
                    //$this->SendDebug('Match', 'exported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValue('Exported', $value);
                    }
                    return '';

                case $cpBase . '/powers/1':
                    //$this->SendDebug('Match', 'powers/1', 0);
                    $this->SetFloatIfNumeric('PowerL1', $payload);
                    return '';

                case $cpBase . '/powers/2':
                    //$this->SendDebug('Match', 'powers/2', 0);
                    $this->SetFloatIfNumeric('PowerL2', $payload);
                    return '';

                case $cpBase . '/powers/3':
                    //$this->SendDebug('Match', 'powers/3', 0);
                    $this->SetFloatIfNumeric('PowerL3', $payload);
                    return '';

                case $cpBase . '/frequency':
                    //$this->SendDebug('Match', 'frequency', 0);
                    $this->SetFloatIfNumeric('Frequency', $payload);
                    return '';

                case $cpBase . '/power_factors/1':
                    //$this->SendDebug('Match', 'power_factors/1', 0);
                    $this->SetFloatIfNumeric('PowerFactor1', $payload);
                    return '';

                case $cpBase . '/power_factors/2':
                    //$this->SendDebug('Match', 'power_factors/2', 0);
                    $this->SetFloatIfNumeric('PowerFactor2', $payload);
                    return '';

                case $cpBase . '/power_factors/3':
                    //$this->SendDebug('Match', 'power_factors/3', 0);
                    $this->SetFloatIfNumeric('PowerFactor3', $payload);
                    return '';

                case $cpBase . '/serial_number':
                    //$this->SendDebug('Match', 'serial_number', 0);
                    $this->SetValue('SerialNumber', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/soc_timestamp':
                    //$this->SendDebug('Match', 'soc_timestamp', 0);
                    $this->SetValue('SocTimestamp', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/vehicle_id':
                    //$this->SendDebug('Match', 'vehicle_id', 0);
                    $this->SetValue('VehicleID', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/error_timestamp':
                    //$this->SendDebug('Match', 'error_timestamp', 0);
                    $this->SetValue('ErrorTimestamp', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/charging_power':
                    //$this->SendDebug('Match', 'charging_power', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('ChargingPower', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/charging_voltage':
                    //$this->SendDebug('Match', 'charging_voltage', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('ChargingVoltage', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/version':
                    //$this->SendDebug('Match', 'version', 0);
                    $this->SetValue('Version', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/evse_signaling':
                    //$this->SendDebug('Match', 'evse_signaling', 0);
                    $this->SetValue('EvseSignaling', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/max_discharge_power':
                    //$this->SendDebug('Match', 'max_discharge_power', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('MaxDischargePower', (float) $payload);
                    }
                    return '';

                case $cpBase . '/max_charge_power':
                    //$this->SendDebug('Match', 'max_charge_power', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValue('MaxChargePower', (float) $payload);
                    }
                    return '';
                
                
                //Ab hier die Daten für die Variblen mit AKtion
                
                case $cpBase . '/manual_lock':
                    //$this->SendDebug('Match', 'manual_lock', 0);
                    $isLocked = $this->ToBool($payload);
                    $this->SetValue('SetChargePointLock', !$isLocked);
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
            case 'PhasesToUse':
                $Value = (int) $Value;
                if (!in_array($Value, [1, 3], true)) {
                    return;
                }

                $chargeMode = (int) $this->GetValue('SetChargeMode');
                if ($chargeMode !== 0) {
                    $this->SendDebug('PhasesToUse', 'Phasenumschaltung blockiert – nicht im Sofortladen', 0);
                    $this->SetValue('PhasesToUse', $Value);
                    return;
                }

                if ($this->UpdatePhasesInChargeTemplate($Value)) {
                    $this->SetValue('PhasesToUse', $Value);
                }
                break;

            case 'SetChargePower':
                $power = (int)$Value;

                $setup = $this->DetermineBestChargingSetup($power);

                $targetPhases   = $setup['phases'];
                $targetCurrent  = $setup['current'];
                $effectivePower = $setup['power'];

                $this->SetValue('SetChargePower', $power);

                $chargeMode = (int)$this->GetValue('SetChargeMode');
                if ($chargeMode !== 0) {
                    $this->SendDebug(
                        'SetChargePower',
                        'Sollleistung blockiert - nicht im Sofortladen',
                        0
                    );
                    break;
                }

                $currentTargetPhases = (int)$this->GetValue('PhasesToUse');
                if (!in_array($currentTargetPhases, [1, 3], true)) {
                    $currentTargetPhases = 1;
                }

                $phaseChanged = ($targetPhases !== $currentTargetPhases);

                // Strom immer für später merken
                $this->SetBuffer('PendingChargeCurrent', (string)$targetCurrent);

                if ($phaseChanged) {
                    if ($this->IsPhaseSwitchLocked()) {
                        $this->SendDebug(
                            'SetChargePower',
                            'Phasenumschaltung gesperrt',
                            0
                        );
                        break;
                    }

                    if (!$this->UpdatePhasesInChargeTemplate($targetPhases)) {
                        $this->SendDebug(
                            'SetChargePower',
                            'Phasenumschaltung fehlgeschlagen',
                            0
                        );
                        break;
                    }

                    $this->SetValue('PhasesToUse', $targetPhases);

                    $lockTimeSeconds = max(0, (int)$this->ReadPropertyInteger('PhaseSwitchLockTime'));
                    if ($lockTimeSeconds > 0) {
                        $this->SetBuffer('PhaseSwitchLock', '1');
                        $this->SetTimerInterval('PhaseSwitchLockTimer', $lockTimeSeconds * 1000);
                    }

                    // nach Phasenwechsel immer kurz warten
                    $this->SetTimerInterval('ApplyChargeCurrentTimer', 200);
                } else {
                    // gleiche Phase -> Strom direkt senden
                    $this->ApplyPendingChargeCurrent();
                }

                break;

            case 'SetChargeMode':
                $modeString = $this->MapChargeModeIntToString((int) $Value);
                $this->PublishSetTopic($cpSetBase . '/chargemode', $modeString);
                $this->SetValue('SetChargeMode', (int) $Value);
                break;

            case 'SetChargeCurrent':
                $minCurrent = max(6, min(32, (int) $this->ReadPropertyInteger('MinCurrentPerPhase')));
                $maxCurrent = max($minCurrent, min(32, (int) $this->ReadPropertyInteger('MaxCurrentPerPhase')));
                $current = max($minCurrent, min($maxCurrent, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/chargecurrent', (string) $current);
                $this->SetValue('SetChargeCurrent', $current);
                break;

            case 'SetMinimalPvSoc':
                $soc = max(0, min(100, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/minimal_pv_soc', (string) $soc);
                $this->SetValue('SetMinimalPvSoc', $soc);
                break;

            case 'SetMinimalPermanentCurrent':
                $minCurrent = max(6, min(32, (int) $this->ReadPropertyInteger('MinCurrentPerPhase')));
                $maxCurrent = max($minCurrent, min(32, (int) $this->ReadPropertyInteger('MaxCurrentPerPhase')));
                $current = max($minCurrent, min($maxCurrent, (int) $Value));
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
    $this->RegisterProfileIntegerEx('OWB.PhasesToUse', 'Electricity', '', '', [
            [1, '1 Phase', '', -1],
            [3, 'Maximum', '', -1]
        ]);

        $this->RegisterProfileIntegerEx('OWB.ChargeLimitation', 'Power', '', '', [
            [0, 'Aus', '', -1],
            [1, 'Energie', '', -1],
            [2, 'EV-SoC', '', -1]
        ]);

        $this->RegisterProfileInteger('OWB.Watt', 'Electricity', '', ' W', 0, 0, 1);

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
    //Diese funktion ist zum aller Topics ausser Ladeprofil/Phasenumschaltung
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

    private function MQTTCommand(string $relativeTopic, string $payload, bool $retain = false): void
    //Diese funktion ist nur zum senden des Topics für das Ladeprofil/Phasenumschaltung
    {
        $baseTopic = rtrim($this->ReadPropertyString('BaseTopic'), '/');
        $fullTopic = $baseTopic . '/' . ltrim($relativeTopic, '/');

        $data = [
            'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType'       => 3,
            'QualityOfService' => 0,
            'Retain'           => $retain,
            'Topic'            => $fullTopic,
            'Payload'          => bin2hex($payload)
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $this->SendDataToParent($json);
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

        private function UpdatePhasesInChargeTemplate(int $phases): bool
    {
        $json = $this->GetBuffer('ChargeTemplateJSON');
        if ($json === '') {
            $json = $this->ReadAttributeString('ChargeTemplateJSON');
            if ($json !== '') {
                $this->SetBuffer('ChargeTemplateJSON', $json);
            }
        }

        if ($json === '') {
            $this->SendDebug(__FUNCTION__, 'Kein ChargeTemplate vorhanden', 0);
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->SendDebug(__FUNCTION__, 'ChargeTemplate JSON ungültig', 0);
            return false;
        }

        if (!isset($data['chargemode']['instant_charging'])) {
            $this->SendDebug(__FUNCTION__, 'instant_charging im Template nicht gefunden', 0);
            return false;
        }

        $data['chargemode']['instant_charging']['phases_to_use'] = $phases;

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $this->SendDebug(__FUNCTION__, 'JSON Encode fehlgeschlagen', 0);
            return false;
        }

        $chargePointId = (int) $this->ReadPropertyInteger('ChargePointID');
        $topic = 'set/chargepoint/' . $chargePointId . '/set/charge_template';

        // Pending merken, damit altes Echo den Wert nicht zurückdreht
        $this->SetBuffer('PendingPhasesToUse', (string) $phases);

        $this->MQTTCommand($topic, $payload);
        $this->SendDebug(__FUNCTION__, 'Gesendet an ' . $topic . ': ' . $payload, 0);

        $this->SetBuffer('ChargeTemplateJSON', $payload);
        $this->WriteAttributeString('ChargeTemplateJSON', $payload);

        return true;
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
            $value = trim((string)$payload);

            if ($value === '' || strtolower($value) === 'null') {
                return '';
            }

            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                return $decoded;
            }

            $decoded = json_decode('"' . addslashes(trim($value, '"')) . '"', true);
            if (is_string($decoded)) {
                return $decoded;
            }

            return trim($value, '"');
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

    private function UpdateDynamicProfiles(): void
    {
        $minCurrent = max(6, min(32, (int) $this->ReadPropertyInteger('MinCurrentPerPhase')));
        $maxCurrent = max($minCurrent, min(32, (int) $this->ReadPropertyInteger('MaxCurrentPerPhase')));

        $voltage = $this->GetEffectiveVoltage();

        $minPower = (int) round($voltage * $minCurrent);
        $maxPower = (int) round($voltage * 3 * $maxCurrent);

        $ampereProfile = 'OWB.Ampere.' . $this->InstanceID;
        $powerProfile  = 'OWB.TargetPower.' . $this->InstanceID;

        if (!IPS_VariableProfileExists($ampereProfile)) {
            IPS_CreateVariableProfile($ampereProfile, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileIcon($ampereProfile, 'Electricity');
        IPS_SetVariableProfileText($ampereProfile, '', ' A');
        IPS_SetVariableProfileValues($ampereProfile, $minCurrent, $maxCurrent, 1);

        if (!IPS_VariableProfileExists($powerProfile)) {
            IPS_CreateVariableProfile($powerProfile, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileIcon($powerProfile, 'Electricity');
        IPS_SetVariableProfileText($powerProfile, '', ' W');
        IPS_SetVariableProfileValues($powerProfile, $minPower, $maxPower, 10);

        $this->SetVariableCustomProfileIfExists('SetChargeCurrent', $ampereProfile);
        $this->SetVariableCustomProfileIfExists('ConfiguredCurrent', $ampereProfile);
        $this->SetVariableCustomProfileIfExists('SetMinimalPermanentCurrent', $ampereProfile);
        $this->SetVariableCustomProfileIfExists('SetChargePower', $powerProfile);
    }

    private function SetVariableCustomProfileIfExists(string $ident, string $profile): void
    {
        $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($id > 0 && IPS_VariableExists($id)) {
            IPS_SetVariableCustomProfile($id, $profile);
        }
    }

    private function DetermineBestChargingSetup(int $requestedPower): array
    {
        $minCurrent = max(6, min(32, (int)$this->ReadPropertyInteger('MinCurrentPerPhase')));
        $maxCurrent = max($minCurrent, min(32, (int)$this->ReadPropertyInteger('MaxCurrentPerPhase')));

        $voltage = $this->GetEffectiveVoltage();

        $candidates = [];

        foreach ([1, 3] as $phases) {
            $idealCurrent = $requestedPower / ($voltage * $phases);

            $candidateCurrents = [
                (int) floor($idealCurrent),
                (int) round($idealCurrent),
                (int) ceil($idealCurrent)
            ];

            foreach ($candidateCurrents as $current) {
                $current = max($minCurrent, min($maxCurrent, $current));
                $power = $current * $voltage * $phases;
                $diff = abs($power - $requestedPower);

                $key = $phases . '_' . $current;
                $candidates[$key] = [
                    'phases'  => $phases,
                    'current' => $current,
                    'power'   => (int) round($power),
                    'diff'    => $diff
                ];
            }
        }

        usort($candidates, function (array $a, array $b): int {
            if ($a['diff'] === $b['diff']) {
                return $a['phases'] <=> $b['phases'];
            }
            return $a['diff'] <=> $b['diff'];
        });

        return $candidates[0];
    }

    public function ApplyPendingChargeCurrent(): void
    {
        $current = (int) $this->GetBuffer('PendingChargeCurrent');

        if ($current <= 0) {
            $this->SetTimerInterval('ApplyChargeCurrentTimer', 0);
            return;
        }

        $cpSetBase = $this->GetChargePointSetBaseTopic();

        $this->PublishSetTopic(
            $cpSetBase . '/chargecurrent',
            (string)$current
        );

        $this->SetValue('SetChargeCurrent', $current);

        $this->SendDebug(
            'ApplyPendingChargeCurrent',
            'Strom gesendet: ' . $current . ' A',
            0
        );

        $this->SetBuffer('PendingChargeCurrent', '0');

        // Timer wieder stoppen
        $this->SetTimerInterval('ApplyChargeCurrentTimer', 0);
    }

    private function IsPhaseSwitchLocked(): bool
    {
        return $this->GetBuffer('PhaseSwitchLock') === '1';
    }

    public function ClearPhaseSwitchLock(): void
    {
        $this->SetBuffer('PhaseSwitchLock', '0');
        $this->SetTimerInterval('PhaseSwitchLockTimer', 0);

        $this->SendDebug('PhaseSwitchLock', 'Phasenwechsel wieder erlaubt', 0);
    }

    private function GetEffectiveVoltage(): float
    {
        $voltage = 230.0;

        $id = @IPS_GetObjectIDByIdent('Voltage1', $this->InstanceID);
        if ($id > 0 && IPS_VariableExists($id)) {
            $value = GetValue($id);
            if (is_numeric($value)) {
                $value = (float) $value;
                if ($value > 100 && $value < 300) {
                    $voltage = $value;
                }
            }
        }

        return $voltage;
    }

    private function SyncSelectedVariables(): void
    {
        $selected = json_decode($this->ReadPropertyString('SelectedVariables'), true);
        if (!is_array($selected)) {
            $selected = [];
        }

        $selected = array_map('strval', $selected);
        $currentIdents = [];

        foreach ($this->GetAvailableVariables() as $group => $variables) {
            foreach ($variables as $var) {
                $ident = $var['ident'];

                if (!in_array($ident, $selected, true)) {
                    continue;
                }

                $currentIdents[] = $ident;

                if (@$this->GetIDForIdent($ident) === false) {
                    switch ($var['type']) {
                        case VARIABLETYPE_INTEGER:
                            $this->RegisterVariableInteger($ident, $var['name'], $var['profile'], $var['pos']);
                            break;

                        case VARIABLETYPE_FLOAT:
                            $this->RegisterVariableFloat($ident, $var['name'], $var['profile'], $var['pos']);
                            break;

                        case VARIABLETYPE_STRING:
                            $this->RegisterVariableString($ident, $var['name'], $var['profile'], $var['pos']);
                            break;

                        case VARIABLETYPE_BOOLEAN:
                            $this->RegisterVariableBoolean($ident, $var['name'], $var['profile'], $var['pos']);
                            break;
                    }

                    $this->SendDebug('SyncSelectedVariables', 'Variable erstellt: ' . $ident, 0);
                }
            }
        }

        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $obj = IPS_GetObject($childID);
            $ident = $obj['ObjectIdent'];

            if ($this->IsOptionalVariableIdent($ident) && !in_array($ident, $currentIdents, true)) {
                $this->UnregisterVariable($ident);
                $this->SendDebug('SyncSelectedVariables', 'Variable entfernt: ' . $ident, 0);
            }
        }
    }

    private function IsOptionalVariableIdent(string $ident): bool
    {
        foreach ($this->GetAvailableVariables() as $group => $variables) {
            foreach ($variables as $var) {
                if ($var['ident'] === $ident) {
                    return true;
                }
            }
        }

        return false;
    }

    private function GetAvailableVariables(): array
    {
        return [
            'Allgemein' => [
                ['ident' => 'SoC',               'name' => 'EV-SoC',                    'type' => VARIABLETYPE_INTEGER, 'profile' => '~Intensity.100', 'pos' => 10],
                ['ident' => 'ProSoC',            'name' => 'Pro-SoC',                   'type' => VARIABLETYPE_INTEGER, 'profile' => '~Intensity.100', 'pos' => 20],
                ['ident' => 'SocTimestamp',      'name' => 'Pro-SoC Zeitstempel',       'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 25],
                ['ident' => 'ConfiguredCurrent', 'name' => 'EVSE Aktuell',              'type' => VARIABLETYPE_INTEGER, 'profile' => '',               'pos' => 30],
                ['ident' => 'Frequency',         'name' => 'Frequenz',                  'type' => VARIABLETYPE_FLOAT,   'profile' => '~Hertz',        'pos' => 60],
                ['ident' => 'Power',             'name' => 'Ladeleistung',              'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',      'pos' => 100],
                ['ident' => 'PhasesInUse',       'name' => 'Verwendete Phasen',         'type' => VARIABLETYPE_INTEGER, 'profile' => '',               'pos' => 110],
                ['ident' => 'ChargeState',       'name' => 'Ladestatus',                'type' => VARIABLETYPE_BOOLEAN, 'profile' => 'OWB.ChargeState','pos' => 120],
                ['ident' => 'PlugState',         'name' => 'Stecker Status',            'type' => VARIABLETYPE_BOOLEAN, 'profile' => 'OWB.PlugState',  'pos' => 130],
                ['ident' => 'State',             'name' => 'Status',                    'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.LPState',    'pos' => 150],
                ['ident' => 'FaultState',        'name' => 'Fehlerstatus',              'type' => VARIABLETYPE_INTEGER, 'profile' => '',               'pos' => 160],
                ['ident' => 'FaultString',       'name' => 'Fehlertext',                'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 170],
                ['ident' => 'ErrorTimestamp',    'name' => 'Fehler Zeitstempel',        'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 175],
                ['ident' => 'StateString',       'name' => 'Statustext',                'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 180],
                ['ident' => 'VehicleName',       'name' => 'Fahrzeug Name',             'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 190],
                ['ident' => 'RFID',              'name' => 'RFID',                      'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 200],
                ['ident' => 'RFIDTimestamp',     'name' => 'RFID Zeitstempel',          'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 205],
                ['ident' => 'DailyImported',     'name' => 'Energie Tag',               'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',   'pos' => 210],
                ['ident' => 'DailyExported',     'name' => 'Energie Tag Export',        'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',   'pos' => 215],
                ['ident' => 'Imported',          'name' => 'Energie Gesamt',            'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',   'pos' => 220],
                ['ident' => 'Exported',          'name' => 'Energie Gesamt Export',     'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',   'pos' => 225],
                ['ident' => 'SerialNumber',      'name' => 'Seriennummer',              'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 237],
                ['ident' => 'VehicleID',         'name' => 'Fahrzeug ID',               'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 238],
                ['ident' => 'Version',           'name' => 'Version',                   'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 239],
                ['ident' => 'EvseSignaling',     'name' => 'EVSE Signaling',            'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 240],
                ['ident' => 'Revision',          'name' => 'Revision',                  'type' => VARIABLETYPE_STRING,  'profile' => '',               'pos' => 241],
                ['ident' => 'ChargingPower',     'name' => 'Aktuelle Ladeleistung',     'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.Watt',       'pos' => 242],
                ['ident' => 'ChargingVoltage',   'name' => 'Aktuelle Ladespannung',     'type' => VARIABLETYPE_INTEGER, 'profile' => '~Volt',          'pos' => 243],
                ['ident' => 'MaxDischargePower', 'name' => 'Max. Entladeleistung',      'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',       'pos' => 244],
                ['ident' => 'MaxChargePower',    'name' => 'Max. Ladeleistung',         'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',       'pos' => 245],
            ],
            'Ströme / Spannungen' => [
                ['ident' => 'PhaseCurrent1', 'name' => 'Strom Phase 1',    'type' => VARIABLETYPE_FLOAT, 'profile' => '~Ampere', 'pos' => 40],
                ['ident' => 'PhaseCurrent2', 'name' => 'Strom Phase 2',    'type' => VARIABLETYPE_FLOAT, 'profile' => '~Ampere', 'pos' => 41],
                ['ident' => 'PhaseCurrent3', 'name' => 'Strom Phase 3',    'type' => VARIABLETYPE_FLOAT, 'profile' => '~Ampere', 'pos' => 42],
                ['ident' => 'Voltage1',      'name' => 'Spannung Phase 1', 'type' => VARIABLETYPE_FLOAT, 'profile' => '~Volt',   'pos' => 50],
                ['ident' => 'Voltage2',      'name' => 'Spannung Phase 2', 'type' => VARIABLETYPE_FLOAT, 'profile' => '~Volt',   'pos' => 51],
                ['ident' => 'Voltage3',      'name' => 'Spannung Phase 3', 'type' => VARIABLETYPE_FLOAT, 'profile' => '~Volt',   'pos' => 52],
            ],
            'Leistung / Faktor' => [
                ['ident' => 'PowerL1',       'name' => 'Leistung Phase 1',         'type' => VARIABLETYPE_FLOAT, 'profile' => 'OWB.Watt', 'pos' => 70],
                ['ident' => 'PowerL2',       'name' => 'Leistung Phase 2',         'type' => VARIABLETYPE_FLOAT, 'profile' => 'OWB.Watt', 'pos' => 71],
                ['ident' => 'PowerL3',       'name' => 'Leistung Phase 3',         'type' => VARIABLETYPE_FLOAT, 'profile' => 'OWB.Watt', 'pos' => 72],
                ['ident' => 'PowerFactor1',  'name' => 'Leistungsfaktor Phase 1',  'type' => VARIABLETYPE_FLOAT, 'profile' => '',         'pos' => 80],
                ['ident' => 'PowerFactor2',  'name' => 'Leistungsfaktor Phase 2',  'type' => VARIABLETYPE_FLOAT, 'profile' => '',         'pos' => 81],
                ['ident' => 'PowerFactor3',  'name' => 'Leistungsfaktor Phase 3',  'type' => VARIABLETYPE_FLOAT, 'profile' => '',         'pos' => 82],
            ]
        ];
    }
}