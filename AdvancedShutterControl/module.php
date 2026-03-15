<?php
declare(strict_types=1);

class AdvancedShutterControl extends IPSModule
{
    private const VM_UPDATE = 10603;

    private const ACTION_OPEN = 1;
    private const ACTION_CLOSE = 2;
    private const ACTION_SHADE = 3;
    private const ACTION_VENTILATE = 4;

    private bool $applyingPosition = false;

    /* ================= Lifecycle ================= */
    public function Create()
    {
        parent::Create();

        // ---- Properties: Shutters ----
        $this->RegisterPropertyString('Shutters', '[]');

        // ---- Attributes ----
        $this->RegisterAttributeInteger('ScheduleEventID', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Track which variables already exist before MaintainVariable calls
        $existingVars = [];
        foreach ([
            'TargetPosition', 'ShutterMode', 'ShadePosition', 'VentilatePosition',
            'ManualOperationBlocked'
        ] as $ident) {
            $id = @$this->GetIDForIdent($ident);
            if ($id && @IPS_VariableExists($id)) {
                $existingVars[$ident] = true;
            }
        }

        // Get presentation IDs dynamically
        $sliderPresentationID = $this->getPresentationIDByCaption('Slider');
        $enumerationPresentationID = $this->getPresentationIDByCaption('Enumeration');
        $switchPresentationID = $this->getPresentationIDByCaption('Switch');

        // Position slider presentation config
        $positionPresentation = [
            'PRESENTATION' => $sliderPresentationID,
            'ICON' => 'Jalousie',
            'MIN' => 0,
            'MAX' => 100,
            'STEP_SIZE' => 1,
            'SUFFIX' => ' %',
            'DIGITS' => 0,
            'USAGE_TYPE' => 0,
            'GRADIENT_TYPE' => 0
        ];

        // Target Position variable (position 1) - Slider 0-100%
        $this->MaintainVariable('TargetPosition', $this->Translate('Target Position'), VARIABLETYPE_INTEGER, $positionPresentation, 1, true);
        $this->EnableAction('TargetPosition');
        $this->initializeVariableDefault('TargetPosition', 0, $existingVars);

        // Shutter Mode variable (position 2) - Enumeration
        $shutterModeOptions = json_encode([
            ['Value' => 0, 'Caption' => $this->Translate('Open'), 'IconActive' => true, 'IconValue' => 'Jalousie', 'Color' => 0x4CAF50],
            ['Value' => 1, 'Caption' => $this->Translate('Closed'), 'IconActive' => true, 'IconValue' => 'Jalousie', 'Color' => 0x9E9E9E],
            ['Value' => 2, 'Caption' => $this->Translate('Shade'), 'IconActive' => true, 'IconValue' => 'Sun', 'Color' => 0xFF9800],
            ['Value' => 3, 'Caption' => $this->Translate('Ventilate'), 'IconActive' => true, 'IconValue' => 'Window', 'Color' => 0x03A9F4]
        ]);
        $this->MaintainVariable('ShutterMode', $this->Translate('Shutter Mode'), VARIABLETYPE_INTEGER, [
            'PRESENTATION' => $enumerationPresentationID,
            'ICON' => 'Jalousie',
            'OPTIONS' => $shutterModeOptions
        ], 2, true);
        $this->EnableAction('ShutterMode');
        $this->initializeVariableDefault('ShutterMode', 0, $existingVars);

        // Shade Position variable (position 3)
        $this->MaintainVariable('ShadePosition', $this->Translate('Shade Position'), VARIABLETYPE_INTEGER, $positionPresentation, 3, true);
        $this->EnableAction('ShadePosition');
        $this->initializeVariableDefault('ShadePosition', 50, $existingVars);

        // Ventilate Position variable (position 4)
        $this->MaintainVariable('VentilatePosition', $this->Translate('Ventilate Position'), VARIABLETYPE_INTEGER, $positionPresentation, 4, true);
        $this->EnableAction('VentilatePosition');
        $this->initializeVariableDefault('VentilatePosition', 10, $existingVars);

        // Manual Operation Blocked variable (position 5) - Switch with lock icon
        $lockSwitchPresentation = [
            'PRESENTATION' => $switchPresentationID,
            'ICON_ON' => 'Lock',
            'ICON_OFF' => 'LockOpen',
            'COLOR_ON' => 0xF44336,
            'COLOR_OFF' => 0x9E9E9E
        ];
        $this->MaintainVariable('ManualOperationBlocked', $this->Translate('Manual Operation Blocked'), VARIABLETYPE_BOOLEAN, $lockSwitchPresentation, 5, true);
        $this->EnableAction('ManualOperationBlocked');
        $this->initializeVariableDefault('ManualOperationBlocked', false, $existingVars);

        // Create or update the weekly schedule event
        $this->maintainScheduleEvent();

        // Register message subscriptions
        $this->registerMessages();

        // Update status
        $shutters = $this->getShutters();
        if (empty($shutters)) {
            $this->SetStatus(104); // No shutters configured
        } else {
            $this->SetStatus(102); // Active
        }
    }

    public function Destroy()
    {
        // Clean up schedule event if this instance is being deleted
        $scheduleID = $this->ReadAttributeInteger('ScheduleEventID');
        if ($scheduleID > 0 && @IPS_EventExists($scheduleID)) {
            IPS_DeleteEvent($scheduleID);
        }

        parent::Destroy();
    }

    /* ================= Configuration Form ================= */
    public function GetConfigurationForm(): string
    {
        $scheduleID = $this->ReadAttributeInteger('ScheduleEventID');

        return json_encode([
            'elements' => [
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Shutters',
                    'expanded' => true,
                    'items' => [
                        [
                            'type' => 'List',
                            'name' => 'Shutters',
                            'caption' => 'Roller Shutters / Blinds',
                            'rowCount' => 5,
                            'add' => true,
                            'delete' => true,
                            'columns' => [
                                [
                                    'caption' => 'Shutter Variable',
                                    'name' => 'ShutterID',
                                    'width' => '300px',
                                    'add' => 0,
                                    'edit' => [
                                        'type' => 'SelectVariable',
                                        'validVariableTypes' => [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT]
                                    ]
                                ],
                                [
                                    'caption' => 'Name',
                                    'name' => 'Name',
                                    'width' => 'auto',
                                    'add' => '',
                                    'edit' => [
                                        'type' => 'ValidationTextBox'
                                    ]
                                ],
                                [
                                    'caption' => 'Open Position (%)',
                                    'name' => 'OpenPosition',
                                    'width' => '220px',
                                    'add' => 0,
                                    'edit' => [
                                        'type' => 'NumberSpinner',
                                        'minimum' => 0,
                                        'maximum' => 100
                                    ]
                                ],
                                [
                                    'caption' => 'Closed Position (%)',
                                    'name' => 'ClosePosition',
                                    'width' => '220px',
                                    'add' => 100,
                                    'edit' => [
                                        'type' => 'NumberSpinner',
                                        'minimum' => 0,
                                        'maximum' => 100
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Weekly Schedule',
                    'items' => [
                        [
                            'type' => 'Label',
                            'caption' => 'The weekly schedule is managed via IP-Symcon\'s built-in schedule event.'
                        ],
                        [
                            'type' => 'Label',
                            'caption' => 'Configure Open, Close, Shade, and Ventilate times in the schedule below the instance.'
                        ],
                        [
                            'type' => 'OpenObjectButton',
                            'objectID' => $scheduleID,
                            'caption' => 'Open Weekly Schedule'
                        ]
                    ]
                ]
            ],
            'status' => [
                [
                    'code' => 102,
                    'icon' => 'active',
                    'caption' => 'Module is active'
                ],
                [
                    'code' => 104,
                    'icon' => 'inactive',
                    'caption' => 'No shutters configured'
                ]
            ]
        ]);
    }

    /* ================= Action Handling ================= */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'TargetPosition':
                $val = max(0, min(100, (int)$Value));
                SetValue($this->GetIDForIdent('TargetPosition'), $val);
                $this->ApplyPosition();
                break;

            case 'ShutterMode':
                $mode = max(0, min(3, (int)$Value));
                SetValue($this->GetIDForIdent('ShutterMode'), $mode);
                $this->applyShutterMode($mode);
                break;

            case 'ShadePosition':
                $val = max(0, min(100, (int)$Value));
                SetValue($this->GetIDForIdent('ShadePosition'), $val);
                if ($this->getCurrentMode() === 2) {
                    SetValue($this->GetIDForIdent('TargetPosition'), $val);
                    $this->ApplyPosition();
                }
                break;

            case 'VentilatePosition':
                $val = max(0, min(100, (int)$Value));
                SetValue($this->GetIDForIdent('VentilatePosition'), $val);
                if ($this->getCurrentMode() === 3) {
                    SetValue($this->GetIDForIdent('TargetPosition'), $val);
                    $this->ApplyPosition();
                }
                break;

            case 'ManualOperationBlocked':
                SetValue($this->GetIDForIdent('ManualOperationBlocked'), (bool)$Value);
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    /* ================= Schedule Action Handler ================= */
    /**
     * Called by the weekly schedule event when action changes
     * @param int $ActionID The action ID from the schedule
     */
    public function ScheduleAction(int $ActionID): void
    {
        $mode = 0;
        switch ($ActionID) {
            case self::ACTION_OPEN:
                $mode = 0;
                break;
            case self::ACTION_CLOSE:
                $mode = 1;
                break;
            case self::ACTION_SHADE:
                $mode = 2;
                break;
            case self::ACTION_VENTILATE:
                $mode = 3;
                break;
        }

        SetValue($this->GetIDForIdent('ShutterMode'), $mode);
        $this->applyShutterMode($mode);
    }

    /* ================= Message Sink ================= */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message !== self::VM_UPDATE) {
            return;
        }

        // Check if sender is one of our shutters
        $shutters = $this->getShutters();
        foreach ($shutters as $shutter) {
            if ((int)$shutter['ShutterID'] === $SenderID) {
                $this->handleShutterChange($SenderID, $Data);
                return;
            }
        }
    }

    /* ================= Public Functions ================= */

    /**
     * Set the target position for all shutters
     * @param int $Position Target position 0-100%
     */
    public function SetTargetPosition(int $Position): void
    {
        $pos = max(0, min(100, $Position));
        SetValue($this->GetIDForIdent('TargetPosition'), $pos);
        $this->ApplyPosition();
    }

    /**
     * Apply the current target position to all shutters.
     * TargetPosition is a logical percentage: 0% = fully open, 100% = fully closed.
     * Each shutter's actual actuator value is scaled through its OpenPosition/ClosePosition range.
     */
    public function ApplyPosition(): void
    {
        $this->applyingPosition = true;
        try {
            $targetPct = (int)@GetValue($this->GetIDForIdent('TargetPosition'));
            $shutters = $this->getShutters();

            foreach ($shutters as $shutter) {
                $shutterID = (int)$shutter['ShutterID'];
                if ($shutterID > 0 && @IPS_VariableExists($shutterID)) {
                    $actuatorValue = $this->scaleToActuator($targetPct, $shutter);
                    $this->sendToShutter($shutterID, $actuatorValue);
                }
            }
        } finally {
            $this->applyingPosition = false;
        }
    }

    /**
     * Set the shutter mode
     * @param int $Mode 0=Open, 1=Close, 2=Shade, 3=Ventilate
     */
    public function SetShutterMode(int $Mode): void
    {
        $mode = max(0, min(3, $Mode));
        SetValue($this->GetIDForIdent('ShutterMode'), $mode);
        $this->applyShutterMode($mode);
    }

    /**
     * Open all shutters
     */
    public function SetOpen(): void
    {
        $this->SetShutterMode(0);
    }

    /**
     * Close all shutters
     */
    public function SetClose(): void
    {
        $this->SetShutterMode(1);
    }

    /**
     * Set all shutters to shade position
     */
    public function SetShade(): void
    {
        $this->SetShutterMode(2);
    }

    /**
     * Set all shutters to ventilate position
     */
    public function SetVentilate(): void
    {
        $this->SetShutterMode(3);
    }

    /**
     * Get the current target position
     * @return int Target position 0-100%
     */
    public function GetTargetPosition(): int
    {
        return (int)@GetValue($this->GetIDForIdent('TargetPosition'));
    }

    /**
     * Get the schedule event ID
     * @return int Event ID or 0 if not exists
     */
    public function GetScheduleEventID(): int
    {
        return $this->ReadAttributeInteger('ScheduleEventID');
    }

    /* ================= Private Helper Functions ================= */

    private function getShutters(): array
    {
        $raw = @json_decode($this->ReadPropertyString('Shutters'), true);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter($raw, function ($shutter) {
            $id = (int)($shutter['ShutterID'] ?? 0);
            return $id > 0 && @IPS_VariableExists($id);
        }));
    }

    private function registerMessages(): void
    {
        // Unregister all previous
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Register shutter variables
        $shutters = $this->getShutters();
        foreach ($shutters as $shutter) {
            $shutterID = (int)$shutter['ShutterID'];
            if ($shutterID > 0) {
                $this->RegisterMessage($shutterID, self::VM_UPDATE);
            }
        }
    }

    private function getCurrentMode(): int
    {
        $modeID = @$this->GetIDForIdent('ShutterMode');
        if ($modeID && @IPS_VariableExists($modeID)) {
            return (int)@GetValue($modeID);
        }
        return 0; // Default: Open
    }

    private function applyShutterMode(int $mode): void
    {
        $targetPct = 0;

        switch ($mode) {
            case 0: // Open
                $targetPct = 0;
                break;
            case 1: // Close
                $targetPct = 100;
                break;
            case 2: // Shade
                $targetPct = (int)@GetValue($this->GetIDForIdent('ShadePosition'));
                break;
            case 3: // Ventilate
                $targetPct = (int)@GetValue($this->GetIDForIdent('VentilatePosition'));
                break;
        }

        SetValue($this->GetIDForIdent('TargetPosition'), $targetPct);
        $this->ApplyPosition();
    }

    private function handleShutterChange(int $shutterID, array $data): void
    {
        // Ignore updates triggered by our own ApplyPosition calls
        if ($this->applyingPosition) {
            return;
        }

        // Find the shutter config for the sender
        $senderShutter = null;
        $shutters = $this->getShutters();
        foreach ($shutters as $shutter) {
            if ((int)$shutter['ShutterID'] === $shutterID) {
                $senderShutter = $shutter;
                break;
            }
        }
        if ($senderShutter === null) {
            return;
        }

        // Convert the raw actuator value to a logical percentage
        $rawValue = isset($data[0]) ? (float)$data[0] : 0.0;
        $newPct = $this->scaleToPercent($rawValue, $senderShutter);
        $currentPct = (int)@GetValue($this->GetIDForIdent('TargetPosition'));

        // Check if the change was significant (not just our own update)
        if (abs($newPct - $currentPct) < 1) {
            return;
        }

        // Revert to current target if manual operation is blocked
        if ($this->isManualOperationBlocked()) {
            $expectedActuator = $this->scaleToActuator($currentPct, $senderShutter);
            $this->sendToShutter($shutterID, $expectedActuator);
            return;
        }

        // Manual operation allowed - update target percentage and sync all shutters
        SetValue($this->GetIDForIdent('TargetPosition'), $newPct);
        $this->ApplyPosition();
    }

    private function isManualOperationBlocked(): bool
    {
        $varID = @$this->GetIDForIdent('ManualOperationBlocked');
        if ($varID && @IPS_VariableExists($varID)) {
            return (bool)@GetValue($varID);
        }
        return false;
    }

    /**
     * Send an actuator value to a single shutter (no scaling, raw value).
     */
    private function sendToShutter(int $shutterID, float $actuatorValue): void
    {
        $current = (float)@GetValue($shutterID);
        $varType = IPS_GetVariable($shutterID)['VariableType'];
        $targetValue = ($varType === VARIABLETYPE_FLOAT) ? $actuatorValue : (int)round($actuatorValue);
        if (abs($current - $actuatorValue) > 0.5) {
            @RequestAction($shutterID, $targetValue);
        }
    }

    /**
     * Scale a logical percentage (0 = open, 100 = closed) to an actuator value
     * using the shutter's individual OpenPosition and ClosePosition.
     */
    private function scaleToActuator(int $percent, array $shutter): float
    {
        $open  = (int)($shutter['OpenPosition']  ?? 0);
        $close = (int)($shutter['ClosePosition'] ?? 100);
        return $open + ($close - $open) * ($percent / 100.0);
    }

    /**
     * Reverse-scale an actuator value back to a logical percentage (0 = open, 100 = closed).
     */
    private function scaleToPercent(float $actuatorValue, array $shutter): int
    {
        $open  = (int)($shutter['OpenPosition']  ?? 0);
        $close = (int)($shutter['ClosePosition'] ?? 100);
        if ($open === $close) {
            return 0;
        }
        $pct = ($actuatorValue - $open) / ($close - $open) * 100.0;
        return max(0, min(100, (int)round($pct)));
    }

    private function maintainScheduleEvent(): void
    {
        $scheduleID = $this->ReadAttributeInteger('ScheduleEventID');

        // Check if existing schedule event still exists
        if ($scheduleID > 0 && !@IPS_EventExists($scheduleID)) {
            $scheduleID = 0;
        }

        // Check if existing schedule has correct number of groups (7) and actions (4)
        if ($scheduleID > 0) {
            $event = @IPS_GetEvent($scheduleID);
            if ($event && (count($event['ScheduleGroups']) !== 7 || count($event['ScheduleActions']) !== 4)) {
                IPS_DeleteEvent($scheduleID);
                $scheduleID = 0;
            }
        }

        // Create new schedule event if needed
        if ($scheduleID === 0) {
            $scheduleID = IPS_CreateEvent(2); // 2 = Schedule event
            IPS_SetParent($scheduleID, $this->InstanceID);
            IPS_SetName($scheduleID, $this->Translate('Weekly Schedule'));
            IPS_SetEventActive($scheduleID, true);

            // Set up schedule groups for each day individually (7 groups)
            IPS_SetEventScheduleGroup($scheduleID, 0, 1);   // Monday
            IPS_SetEventScheduleGroup($scheduleID, 1, 2);   // Tuesday
            IPS_SetEventScheduleGroup($scheduleID, 2, 4);   // Wednesday
            IPS_SetEventScheduleGroup($scheduleID, 3, 8);   // Thursday
            IPS_SetEventScheduleGroup($scheduleID, 4, 16);  // Friday
            IPS_SetEventScheduleGroup($scheduleID, 5, 32);  // Saturday
            IPS_SetEventScheduleGroup($scheduleID, 6, 64);  // Sunday

            // Set up schedule actions: Open, Close, Shade, Ventilate
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_OPEN, $this->Translate('Open'), 0x4CAF50, 'ASC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_OPEN . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_CLOSE, $this->Translate('Closed'), 0x9E9E9E, 'ASC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_CLOSE . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_SHADE, $this->Translate('Shade'), 0xFF9800, 'ASC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_SHADE . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_VENTILATE, $this->Translate('Ventilate'), 0x03A9F4, 'ASC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_VENTILATE . ');');

            // Set default schedule points for all days - Open
            for ($group = 0; $group <= 6; $group++) {
                IPS_SetEventScheduleGroupPoint($scheduleID, $group, 0, 0, 0, 0, self::ACTION_OPEN);
            }

            $this->WriteAttributeInteger('ScheduleEventID', $scheduleID);
        }
    }

    private function initializeVariableDefault(string $ident, $defaultValue, array $existingVars = []): void
    {
        // Only set defaults for newly created variables
        if (isset($existingVars[$ident])) {
            return;
        }

        $varID = @$this->GetIDForIdent($ident);
        if (!$varID || !@IPS_VariableExists($varID)) {
            return;
        }

        SetValue($varID, $defaultValue);
    }

    private function getPresentationIDByCaption(string $caption): string
    {
        $presentationsData = IPS_GetPresentations();
        $presentations = is_string($presentationsData) ? json_decode($presentationsData, true) : $presentationsData;
        if (!is_array($presentations)) {
            return '';
        }
        foreach ($presentations as $presentation) {
            if (isset($presentation['caption']) && $presentation['caption'] === $caption) {
                return $presentation['id'];
            }
        }
        return '';
    }
}
