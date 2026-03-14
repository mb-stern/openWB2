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

        $this->RegisterProfiles();

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
        $this->SyncVariables();
        $this->UpdateDynamicProfiles();
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
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'Auswahl der anzulegenden Variablen'
                ],
                [
                    'name'    => 'SelectedVariables',
                    'type'    => 'List',
                    'rowCount'=> 18,
                    'add'     => false,
                    'delete'  => false,
                    'columns' => [
                        [
                            'caption' => 'Aktiv',
                            'name'    => 'enabled',
                            'width'   => '70px',
                            'edit'    => ['type' => 'CheckBox']
                        ],
                        [
                            'caption' => 'Bereich',
                            'name'    => 'group',
                            'width'   => '180px',
                            'edit'    => ['type' => 'ValidationTextBox'],
                            'save'    => false
                        ],
                        [
                            'caption' => 'Ident',
                            'name'    => 'ident',
                            'width'   => '220px',
                            'edit'    => ['type' => 'ValidationTextBox'],
                            'save'    => false
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'caption',
                            'width'   => 'auto',
                            'edit'    => ['type' => 'ValidationTextBox'],
                            'save'    => false
                        ]
                    ],
                    'values' => $this->GetVariableSelectionValues()
                ]
            ],
            'actions' => [
                [
                    "type" => "Label",
                    "caption" => "Sag danke und unterstütze den Modulentwickler:"
                ],
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "Image",
                            "onClick" => "echo 'https://paypal.me/mbstern';",
                            "image" => "data:image/jpeg;base64,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"
                        ],
                        [
                            "type" => "Label",
                            "caption" => ""
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
                    $this->SetValueSafe('PhasesToUse', $receivedPhases);
                } else {
                    // Wenn openWB den gewünschten Wert zurückmeldet, übernehmen und Pending löschen
                    if ($receivedPhases === $pendingPhases) {
                        $this->SetValueSafe('PhasesToUse', $receivedPhases);
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
                        $this->SetValueSafe('SoC', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/pro_soc':
                    //$this->SendDebug('Match', 'pro_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('ProSoC', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/evse_current':
                    //$this->SendDebug('Match', 'evse_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('ConfiguredCurrent', (int) round((float) $payload));
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
                        $this->SetValueSafe('PhasesInUse', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/charge_state':
                    //$this->SendDebug('Match', 'charge_state', 0);
                    $value = $this->ToBool($payload);
                    $this->SetValueSafe('ChargeState', $value);
        
                    $this->UpdateLPState();
                    return '';

                case $cpBase . '/plug_state':
                    //$this->SendDebug('Match', 'plug_state', 0);
                    $value = $this->ToBool($payload);
                    $this->SetValueSafe('PlugState', $value);
                    $this->UpdateLPState();
                    return '';

                case $cpBase . '/fault_state':
                    //$this->SendDebug('Match', 'fault_state', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('FaultState', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/fault_str':
                    //$this->SendDebug('Match', 'fault_str', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValueSafe('FaultString', $value);
                    return '';

                case $cpBase . '/state_str':
                    //$this->SendDebug('Match', 'state_str', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValueSafe('StateString', $value);
                    return '';

                case $cpBase . '/vehicle_name':
                    //$this->SendDebug('Match', 'vehicle_name', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValueSafe('VehicleName', $value);
                    return '';

                case $cpBase . '/rfid':
                    //$this->SendDebug('Match', 'rfid', 0);
                    $value = $this->PayloadToString($payload);
                    $value = json_decode('"' . addslashes($value) . '"');
                    $this->SetValueSafe('RFID', $value);
                    return '';

                case $cpBase . '/daily_imported':
                    //$this->SendDebug('Match', 'daily_imported', 0);
                    $value = ((float) $payload) / 1000;
                    $this->SetValueSafe('DailyImported', $value);
                    return '';

                case $cpBase . '/imported':
                    //$this->SendDebug('Match', 'imported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValueSafe('Imported', $value);
                    }
                    return '';

                case $cpBase . '/rfid_timestamp':
                    //$this->SendDebug('Match', 'rfid_timestamp', 0);
                    $this->SetValueSafe('RFIDTimestamp', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/daily_exported':
                    //$this->SendDebug('Match', 'daily_exported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValueSafe('DailyExported', $value);
                    }
                    return '';

                case $cpBase . '/exported':
                    //$this->SendDebug('Match', 'exported', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = ((float) $payload) / 1000;
                        $this->SetValueSafe('Exported', $value);
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
                    $this->SetValueSafe('SerialNumber', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/soc_timestamp':
                    //$this->SendDebug('Match', 'soc_timestamp', 0);
                    $this->SetValueSafe('SocTimestamp', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/vehicle_id':
                    //$this->SendDebug('Match', 'vehicle_id', 0);
                    $this->SetValueSafe('VehicleID', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/error_timestamp':
                    //$this->SendDebug('Match', 'error_timestamp', 0);
                    $this->SetValueSafe('ErrorTimestamp', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/charging_power':
                    //$this->SendDebug('Match', 'charging_power', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('ChargingPower', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/charging_voltage':
                    //$this->SendDebug('Match', 'charging_voltage', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('ChargingVoltage', (int) round((float) $payload));
                    }
                    return '';

                case $cpBase . '/version':
                    //$this->SendDebug('Match', 'version', 0);
                    $this->SetValueSafe('Version', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/evse_signaling':
                    //$this->SendDebug('Match', 'evse_signaling', 0);
                    $this->SetValueSafe('EvseSignaling', $this->PayloadToString($payload));
                    return '';

                case $cpBase . '/max_discharge_power':
                    //$this->SendDebug('Match', 'max_discharge_power', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('MaxDischargePower', (float) $payload);
                    }
                    return '';

                case $cpBase . '/max_charge_power':
                    //$this->SendDebug('Match', 'max_charge_power', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $this->SetValueSafe('MaxChargePower', (float) $payload);
                    }
                    return '';
                
                
                //Ab hier die Daten für die Variblen mit AKtion
                
                case $cpBase . '/manual_lock':
                    //$this->SendDebug('Match', 'manual_lock', 0);
                    $isLocked = $this->ToBool($payload);
                    $this->SetValueSafe('SetChargePointLock', !$isLocked);
                    return '';

                case $cpBase . '/chargemode':
                    //$this->SendDebug('Match', 'chargemode', 0);
                    $value = $this->MapChargeModeStringToInt($this->PayloadToString($payload));
                    $this->SetValueSafe('SetChargeMode', $value);
                    return '';

                case $cpBase . '/instant_charging_limit':
                    //$this->SendDebug('Match', 'instant_charging_limit', 0);
                    $value = $this->MapLimitTypeStringToInt($this->PayloadToString($payload));
                    $this->SetValueSafe('SetInstantChargingLimit', $value);
                    return '';

                case $cpBase . '/instant_charging_limit_soc':
                    //$this->SendDebug('Match', 'instant_charging_limit_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValueSafe('SetInstantChargingLimitSoc', $value);
                    }
                    return '';

                case $cpBase . '/instant_charging_limit_amount':
                    //$this->SendDebug('Match', 'instant_charging_limit_amount', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValueSafe('SetInstantChargingLimitAmount', $value);
                    }
                    return '';

                case $cpBase . '/charging_current':
                    //$this->SendDebug('Match', 'charging_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValueSafe('SetChargeCurrent', $value);
                    }
                    return '';

                case $cpBase . '/minimal_pv_soc':
                    //$this->SendDebug('Match', 'minimal_pv_soc', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValueSafe('SetMinimalPvSoc', $value);
                    }
                    return '';

                case $cpBase . '/minimal_permanent_current':
                    //$this->SendDebug('Match', 'minimal_permanent_current', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (int) round((float) $payload);
                        $this->SetValueSafe('SetMinimalPermanentCurrent', $value);
                    }
                    return '';

                case $cpBase . '/max_price_eco':
                    //$this->SendDebug('Match', 'max_price_eco', 0);
                    if ($this->IsNumericPayload($payload)) {
                        $value = (float) $payload;
                        if (!is_nan($value) && !is_infinite($value)) {
                            $this->SetValueSafe('SetMaxPriceEco', $value);
                        }
                    }
                    return '';

                case rtrim($this->ReadPropertyString('BaseTopic'), '/') . '/simpleAPI/bat_mode':
                    //$this->SendDebug('Match', 'bat_mode', 0);
                    $value = $this->MapBatModeStringToInt($this->PayloadToString($payload));
                    $this->SetValueSafe('SetBatMode', $value);
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

                $chargeMode = (int) $this->GetValueSafe('SetChargeMode');
                if ($chargeMode !== 0) {
                    $this->SendDebug('PhasesToUse', 'Phasenumschaltung blockiert – nicht im Sofortladen', 0);
                    $this->SetValueSafe('PhasesToUse', $Value);
                    return;
                }

                if ($this->UpdatePhasesInChargeTemplate($Value)) {
                    $this->SetValueSafe('PhasesToUse', $Value);
                }
                break;

            case 'SetChargePower':
                $power = (int)$Value;

                $setup = $this->DetermineBestChargingSetup($power);

                $targetPhases   = $setup['phases'];
                $targetCurrent  = $setup['current'];
                $effectivePower = $setup['power'];

                $this->SetValueSafe('SetChargePower', $power);

                $chargeMode = (int)$this->GetValueSafe('SetChargeMode');
                if ($chargeMode !== 0) {
                    $this->SendDebug(
                        'SetChargePower',
                        'Sollleistung blockiert - nicht im Sofortladen',
                        0
                    );
                    break;
                }

                $currentTargetPhases = (int)$this->GetValueSafe('PhasesToUse');
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

                    $this->SetValueSafe('PhasesToUse', $targetPhases);

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
                $this->SetValueSafe('SetChargeMode', (int) $Value);
                break;

            case 'SetChargeCurrent':
                $minCurrent = max(6, min(32, (int) $this->ReadPropertyInteger('MinCurrentPerPhase')));
                $maxCurrent = max($minCurrent, min(32, (int) $this->ReadPropertyInteger('MaxCurrentPerPhase')));
                $current = max($minCurrent, min($maxCurrent, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/chargecurrent', (string) $current);
                $this->SetValueSafe('SetChargeCurrent', $current);
                break;

            case 'SetMinimalPvSoc':
                $soc = max(0, min(100, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/minimal_pv_soc', (string) $soc);
                $this->SetValueSafe('SetMinimalPvSoc', $soc);
                break;

            case 'SetMinimalPermanentCurrent':
                $minCurrent = max(6, min(32, (int) $this->ReadPropertyInteger('MinCurrentPerPhase')));
                $maxCurrent = max($minCurrent, min(32, (int) $this->ReadPropertyInteger('MaxCurrentPerPhase')));
                $current = max($minCurrent, min($maxCurrent, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/minimal_permanent_current', (string) $current);
                $this->SetValueSafe('SetMinimalPermanentCurrent', $current);
                break;

            case 'SetMaxPriceEco':
                $price = max(0, (float) $Value);
                $payload = number_format($price, 2, '.', '');
                $this->PublishSetTopic($cpSetBase . '/max_price_eco', $payload);
                $this->SetValueSafe('SetMaxPriceEco', $price);
                break;

            case 'SetChargePointLock':
                $enabled = (bool)$Value;
                $payload = $enabled ? 'false' : 'true';
                $this->PublishSetTopic($cpSetBase . '/chargepoint_lock', $payload);
                $this->SetValueSafe('SetChargePointLock', $enabled);
                break;

            case 'SetBatMode':
                $batMode = $this->MapBatModeIntToString((int) $Value);
                $this->PublishSetTopic('bat_mode', $batMode);
                $this->SetValueSafe('SetBatMode', (int) $Value);
                break;

            case 'SetInstantChargingLimit':
                $limitType = $this->MapLimitTypeIntToString((int) $Value);
                $this->PublishSetTopic($cpSetBase . '/instant_charging_limit', $limitType);
                $this->SetValueSafe('SetInstantChargingLimit', (int) $Value);
                break;

            case 'SetInstantChargingLimitSoc':
                $soc = max(0, min(100, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/instant_charging_limit_soc', (string) $soc);
                $this->SetValueSafe('SetInstantChargingLimitSoc', $soc);
                break;

            case 'SetInstantChargingLimitAmount':
                $energy = max(1, min(50, (int) $Value));
                $this->PublishSetTopic($cpSetBase . '/instant_charging_limit_amount', (string) $energy);
                $this->SetValueSafe('SetInstantChargingLimitAmount', $energy);
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
        $plugged = (bool) $this->GetValueSafe('PlugState', false);
        $charging = (bool) $this->GetValueSafe('ChargeState', false);

        if (!$this->HasIdent('State')) {
            return;
        }

        if (!$plugged) {
            $this->SetValueSafe('State', 0);
            return;
        }

        if ($charging) {
            $this->SetValueSafe('State', 2);
            return;
        }

        $this->SetValueSafe('State', 1);
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

        $id = $this->GetIDForIdentSafe('SetChargeCurrent');
        if ($id > 0) {
            IPS_SetVariableCustomProfile($id, $ampereProfile);
        }

        $id = $this->GetIDForIdentSafe('ConfiguredCurrent');
        if ($id > 0) {
            IPS_SetVariableCustomProfile($id, $ampereProfile);
        }

        $id = $this->GetIDForIdentSafe('SetMinimalPermanentCurrent');
        if ($id > 0) {
            IPS_SetVariableCustomProfile($id, $ampereProfile);
        }

        $id = $this->GetIDForIdentSafe('SetChargePower');
        if ($id > 0) {
            IPS_SetVariableCustomProfile($id, $powerProfile);
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

        $this->SetValueSafe('SetChargeCurrent', $current);

        $this->SendDebug(
            'ApplyPendingChargeCurrent',
            'Strom gesendet: ' . $current . ' A',
            0
        );

        $this->SetBuffer('PendingChargeCurrent', '0');
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

        $id = $this->GetIDForIdentSafe('Voltage1');
        if ($id > 0) {
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

    private function GetVariableDefinitions(): array
    {
        return [
            // Status / Read
            ['ident' => 'SoC',                         'caption' => 'EV-SoC',                          'type' => VARIABLETYPE_INTEGER, 'profile' => '~Intensity.100',       'position' => 10,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'ProSoC',                      'caption' => 'Pro-SoC',                         'type' => VARIABLETYPE_INTEGER, 'profile' => '~Intensity.100',       'position' => 20,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'SocTimestamp',                'caption' => 'Pro-SoC Zeitstempel',            'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 25,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'ConfiguredCurrent',           'caption' => 'EVSE Aktuell',                    'type' => VARIABLETYPE_INTEGER, 'profile' => '',                      'position' => 30,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PhaseCurrent1',               'caption' => 'Strom Phase 1',                   'type' => VARIABLETYPE_FLOAT,   'profile' => '~Ampere',              'position' => 40,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PhaseCurrent2',               'caption' => 'Strom Phase 2',                   'type' => VARIABLETYPE_FLOAT,   'profile' => '~Ampere',              'position' => 41,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PhaseCurrent3',               'caption' => 'Strom Phase 3',                   'type' => VARIABLETYPE_FLOAT,   'profile' => '~Ampere',              'position' => 42,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Voltage1',                    'caption' => 'Spannung Phase 1',                'type' => VARIABLETYPE_FLOAT,   'profile' => '~Volt',                'position' => 50,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Voltage2',                    'caption' => 'Spannung Phase 2',                'type' => VARIABLETYPE_FLOAT,   'profile' => '~Volt',                'position' => 51,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Voltage3',                    'caption' => 'Spannung Phase 3',                'type' => VARIABLETYPE_FLOAT,   'profile' => '~Volt',                'position' => 52,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Frequency',                   'caption' => 'Frequenz',                        'type' => VARIABLETYPE_FLOAT,   'profile' => '~Hertz',               'position' => 60,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PowerL1',                     'caption' => 'Leistung Phase 1',                'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',             'position' => 70,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PowerL2',                     'caption' => 'Leistung Phase 2',                'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',             'position' => 71,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PowerL3',                     'caption' => 'Leistung Phase 3',                'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',             'position' => 72,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PowerFactor1',                'caption' => 'Leistungsfaktor Phase 1',         'type' => VARIABLETYPE_FLOAT,   'profile' => '',                      'position' => 80,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PowerFactor2',                'caption' => 'Leistungsfaktor Phase 2',         'type' => VARIABLETYPE_FLOAT,   'profile' => '',                      'position' => 81,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PowerFactor3',                'caption' => 'Leistungsfaktor Phase 3',         'type' => VARIABLETYPE_FLOAT,   'profile' => '',                      'position' => 82,  'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Power',                       'caption' => 'Ladeleistung',                    'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',             'position' => 100, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PhasesInUse',                 'caption' => 'Verwendete Phasen',               'type' => VARIABLETYPE_INTEGER, 'profile' => '',                      'position' => 110, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'ChargeState',                 'caption' => 'Ladestatus',                      'type' => VARIABLETYPE_BOOLEAN, 'profile' => 'OWB.ChargeState',      'position' => 120, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'PlugState',                   'caption' => 'Stecker Status',                  'type' => VARIABLETYPE_BOOLEAN, 'profile' => 'OWB.PlugState',        'position' => 130, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'State',                       'caption' => 'Status',                          'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.LPState',          'position' => 150, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'FaultState',                  'caption' => 'Fehlerstatus',                    'type' => VARIABLETYPE_INTEGER, 'profile' => '',                      'position' => 160, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'FaultString',                 'caption' => 'Fehlertext',                      'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 170, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'ErrorTimestamp',              'caption' => 'Fehler Zeitstempel',              'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 175, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'StateString',                 'caption' => 'Statustext',                      'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 180, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'VehicleName',                 'caption' => 'Fahrzeug Name',                   'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 190, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'RFID',                        'caption' => 'RFID',                            'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 200, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'RFIDTimestamp',               'caption' => 'RFID Zeitstempel',                'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 205, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'DailyImported',               'caption' => 'Energie Tag',                     'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',         'position' => 210, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'DailyExported',               'caption' => 'Energie Tag Export',              'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',         'position' => 215, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Imported',                    'caption' => 'Energie Gesamt',                  'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',         'position' => 220, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Exported',                    'caption' => 'Energie Gesamt Export',           'type' => VARIABLETYPE_FLOAT,   'profile' => '~Electricity',         'position' => 225, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'SerialNumber',                'caption' => 'Seriennummer',                    'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 237, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'VehicleID',                   'caption' => 'Fahrzeug ID',                     'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 238, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Version',                     'caption' => 'Version',                         'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 239, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'EvseSignaling',               'caption' => 'EVSE Signaling',                  'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 240, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'Revision',                    'caption' => 'Revision',                        'type' => VARIABLETYPE_STRING,  'profile' => '',                      'position' => 241, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'ChargingPower',               'caption' => 'Aktuelle Ladeleistung',           'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.Watt',             'position' => 242, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'ChargingVoltage',             'caption' => 'Aktuelle Ladespannung',           'type' => VARIABLETYPE_INTEGER, 'profile' => '~Volt',                'position' => 243, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'MaxDischargePower',           'caption' => 'Max. Entladeleistung',            'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',             'position' => 244, 'action' => false, 'group' => 'Status / Read'],
            ['ident' => 'MaxChargePower',              'caption' => 'Max. Ladeleistung',               'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Watt',             'position' => 245, 'action' => false, 'group' => 'Status / Read'],

            // Action / Write
            ['ident' => 'SetChargePointLock',          'caption' => 'Ladepunkt sperren',               'type' => VARIABLETYPE_BOOLEAN, 'profile' => 'OWB.ChargePointEnabled','position' => 290, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetChargeMode',               'caption' => 'Lademodus',                       'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.ChargeMode',       'position' => 300, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetChargeCurrent',            'caption' => 'Stromstärke',                     'type' => VARIABLETYPE_INTEGER, 'profile' => '',                      'position' => 310, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'PhasesToUse',                 'caption' => 'Phasen Sofortladen',              'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.PhasesToUse',      'position' => 315, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetChargePower',              'caption' => 'Sollleistung',                    'type' => VARIABLETYPE_INTEGER, 'profile' => '',                      'position' => 312, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetMinimalPvSoc',             'caption' => 'Mindes-SoC für das Fahrzeug',     'type' => VARIABLETYPE_INTEGER, 'profile' => '~Intensity.100',       'position' => 320, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetMinimalPermanentCurrent',  'caption' => 'Minimaler Dauerstrom',            'type' => VARIABLETYPE_INTEGER, 'profile' => '',                      'position' => 330, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetMaxPriceEco',              'caption' => 'Höchstpreis Eco',                 'type' => VARIABLETYPE_FLOAT,   'profile' => 'OWB.Price',            'position' => 340, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetBatMode',                  'caption' => 'Ladepriorität',                   'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.BatMode',          'position' => 360, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetInstantChargingLimit',     'caption' => 'Begrenzung',                      'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.ChargeLimitation', 'position' => 370, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetInstantChargingLimitSoc',  'caption' => 'SoC-Limit für das Fahrzeug',      'type' => VARIABLETYPE_INTEGER, 'profile' => '~Intensity.100',       'position' => 380, 'action' => true,  'group' => 'Action / Write'],
            ['ident' => 'SetInstantChargingLimitAmount','caption' => 'Energie Limit',                  'type' => VARIABLETYPE_INTEGER, 'profile' => 'OWB.EnergyToCharge',   'position' => 390, 'action' => true,  'group' => 'Action / Write'],
        ];
    }

    private function GetVariableSelectionValues(): array
    {
        $selected = $this->GetSelectedVariableIdents();
        $values = [];

        foreach ($this->GetVariableDefinitions() as $definition) {
            $values[] = [
                'enabled' => in_array($definition['ident'], $selected, true),
                'group'   => $definition['group'],
                'ident'   => $definition['ident'],
                'caption' => $definition['caption']
            ];
        }

        return $values;
    }

    private function GetSelectedVariableIdents(): array
    {
        $raw = $this->ReadPropertyString('SelectedVariables');
        $data = json_decode($raw, true);

        if (!is_array($data) || $data === []) {
            // Standard: alles aktiv
            return array_column($this->GetVariableDefinitions(), 'ident');
        }

        $idents = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['enabled']) && isset($row['ident']) && $row['ident'] !== '') {
                $idents[] = (string) $row['ident'];
            }
        }

        if ($idents === []) {
            return [];
        }

        return array_values(array_unique($idents));
    }

    private function SyncSelectedVariables(): void
    {
        $raw = $this->ReadPropertyString('SelectedVariables');
        $data = json_decode($raw, true);

        if (is_array($data) && $data !== []) {
            return;
        }

        $all = [];
        foreach ($this->GetVariableDefinitions() as $definition) {
            $all[] = [
                'enabled' => true,
                'group'   => $definition['group'],
                'ident'   => $definition['ident'],
                'caption' => $definition['caption']
            ];
        }

        $this->UpdateFormField('SelectedVariables', 'values', $all);
    }

    private function SyncVariables(): void
    {
        $selected = $this->GetSelectedVariableIdents();
        $definitions = $this->GetVariableDefinitions();

        foreach ($definitions as $definition) {
            $ident = $definition['ident'];
            $exists = $this->HasIdent($ident);
            $enabled = in_array($ident, $selected, true);

            if ($enabled && !$exists) {
                $this->RegisterVariableByDefinition($definition);
            }

            if (!$enabled && $exists) {
                $this->UnregisterVariableByIdent($ident);
            }
        }
    }

    private function RegisterVariableByDefinition(array $definition): void
    {
        $ident    = $definition['ident'];
        $caption  = $definition['caption'];
        $type     = $definition['type'];
        $profile  = $definition['profile'];
        $position = $definition['position'];
        $action   = $definition['action'];

        switch ($type) {
            case VARIABLETYPE_BOOLEAN:
                $this->RegisterVariableBoolean($ident, $caption, $profile, $position);
                break;
            case VARIABLETYPE_INTEGER:
                $this->RegisterVariableInteger($ident, $caption, $profile, $position);
                break;
            case VARIABLETYPE_FLOAT:
                $this->RegisterVariableFloat($ident, $caption, $profile, $position);
                break;
            case VARIABLETYPE_STRING:
                $this->RegisterVariableString($ident, $caption, $profile, $position);
                break;
        }

        if ($action) {
            $this->EnableAction($ident);
        }
    }

    private function UnregisterVariableByIdent(string $ident): void
    {
        $id = $this->GetIDForIdentSafe($ident);
        if ($id > 0 && IPS_ObjectExists($id)) {
            IPS_DeleteVariable($id);
        }
    }

    private function GetIDForIdentSafe(string $ident): int
    {
        $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($id === false || !is_int($id)) {
            return 0;
        }
        return $id;
    }

    private function HasIdent(string $ident): bool
    {
        return $this->GetIDForIdentSafe($ident) > 0;
    }

    private function SetValueSafe(string $ident, $value): void
    {
        $id = $this->GetIDForIdentSafe($ident);
        if ($id <= 0) {
            return;
        }

        SetValue($id, $value);
    }

    private function GetValueSafe(string $ident, $default = null)
    {
        $id = $this->GetIDForIdentSafe($ident);
        if ($id <= 0) {
            return $default;
        }

        return GetValue($id);
    }

    private function SetFloatIfNumeric(string $ident, $payload): void
    {
        if (!$this->HasIdent($ident)) {
            return;
        }

        if (!$this->IsNumericPayload($payload)) {
            $this->SendDebug('SetFloatIfNumeric', $ident . ' Payload nicht numerisch: ' . (string)$payload, 0);
            return;
        }

        $value = (float) $payload;

        if (is_nan($value) || is_infinite($value)) {
            $this->SendDebug('SetFloatIfNumeric', $ident . ' ungültiger Wert: ' . (string)$payload, 0);
            return;
        }

        $this->SetValueSafe($ident, $value);
    }
}