<? class MachineState extends IPSModule {
  public function __construct($InstanceID) {
    parent::__construct($InstanceID);
  }

  public function Create() {
    if (!IPS_VariableProfileExists('MS.State')) {
      IPS_CreateVariableProfile('MS.State', 1);
      IPS_SetVariableProfileAssociation('MS.State', 0, 'Off', 'Power', 0x000000);
      IPS_SetVariableProfileAssociation('MS.State', 1, 'On', 'Power', 0x000000);
      IPS_SetVariableProfileAssociation('MS.State', 2, 'Running', 'Power', 0x000000);
      IPS_SetVariableProfileAssociation('MS.State', 3, 'Done', 'Power', 0x000000);
    }

    parent::Create();

    $this->RegisterPropertyInteger('PowerId', 0);
    $this->RegisterPropertyInteger('EnergyId', 0);
    $this->RegisterPropertyFloat('PowerOff', 1);
    $this->RegisterPropertyBoolean('TimerOff', false);
    $this->RegisterPropertyInteger('DelayOff', 5);
    $this->RegisterPropertyFloat('PowerDone', 3);
    $this->RegisterPropertyInteger('DelayDone', 60);
    $this->RegisterPropertyBoolean('TimerDone', false);

    $this->RegisterPropertyInteger('StartTime', 0);
    $this->RegisterPropertyFloat('StartEnergy', 0);

    $this->RegisterTimer('TimerOff', 0, 'MS_SetState($_IPS[\'TARGET\'], 0);');
    $this->RegisterTimer('TimerDone', 0, 'MS_SetState($_IPS[\'TARGET\'], 3);');

    $this->SetState(0);
  }

  public function ApplyChanges() {
    parent::ApplyChanges();
    if (!$StateID = @$this->GetIDForIdent('STATE')) $this->RegisterVariableInteger('STATE', 'Zustand', 'MS.State', 1);
    if (!$EnergyID = @$this->GetIDForIdent('ENERGY')) $this->RegisterVariableFloat('ENERGY', 'Verbrauch', '~Electricity', 2);
    if (!$DurationID = @$this->GetIDForIdent('DURATION')) {
      $DurationID = $this->RegisterVariableInteger('DURATION', 'Dauer', '', 3);
      IPS_SetIcon($StateID, 'Clock');
    }

    $PowerID = $this->ReadPropertyInteger('PowerId');

    if (!$EventID = @IPS_GetObjectIDByIdent('ON_POWER_CHANGE', $this->InstanceID)) {
      $EventID = IPS_CreateEvent(0);
      IPS_SetParent($EventID, $this->InstanceID);
      IPS_SetIdent($EventID, 'ON_POWER_CHANGE');
      IPS_SetHidden($EventID, true);
      IPS_SetName($EventID, 'On power change');
      IPS_SetPosition($EventID, 999);
    }
    IPS_SetEventTrigger($EventID, 0, $PowerID);
    IPS_SetEventScript($EventID, 'MS_Update($_IPS[\'TARGET\']);');
    IPS_SetEventActive($EventID, true);
  }

  public function Update() {
    $CurrentPower = $this->CurrentPower();
    $StateID = $this->GetIDForIdent('STATE');

    if($CurrentPower < $this->ReadPropertyFloat('PowerOff')) {
      // State: Off
      $this->StartTimerOff();
     } elseif($CurrentPower >= $this->ReadPropertyFloat('PowerDone')) {
       // State: Running
       $this->SetState(2);
     } elseif($CurrentPower >= $this->ReadPropertyFloat('PowerOff') && $this->GetState() == 0) {
       // State: On
       $this->SetState(1);
     } elseif($CurrentPower < $this->ReadPropertyFloat('PowerDone') && $this->GetState() == 2) {
       // State: Done
       $this->StartTimerDone();
     }
  }

  public function CurrentPower() {
    $PowerID = $this->ReadPropertyInteger('PowerId');
    if($PowerID > 0) return (float)GetValue($PowerID);
  }

  public function CurrentEnergy() {
    $EnergyID = $this->ReadPropertyInteger('EnergyId');
    if($EnergyID > 0) return (int)GetValue($EnergyID);
  }

  public function SetState($value) {
    $this->StopTimerOff();
    $this->StopTimerDone();

    if ($value == 0 || $value == 1) {
      IPS_SetProperty($this->InstanceID, 'StartTime', 0);
      IPS_SetProperty($this->InstanceID, 'StartEnergy', 0);
      IPS_ApplyChanges($this->InstanceID);
    } elseif ($value == 2 && $this->ReadPropertyInteger('StartTime') == 0) {
      IPS_SetProperty($this->InstanceID, 'StartTime', time());
      IPS_SetProperty($this->InstanceID, 'StartEnergy', $this->CurrentEnergy());
      IPS_ApplyChanges($this->InstanceID);
    } elseif ($value == 4 && $this->ReadPropertyInteger('StartTime') > 0) {
      IPS_LogMessage('TEST', $this->ReadPropertyInteger('StartTime'));
      SetValueInteger($this->GetIDForIdent('DURATION'), ceil( (time() - $this->ReadPropertyInteger('StartTime')) / 60 ));
      SetValueFloat($this->GetIDForIdent('ENERGY'), ($this->CurrentEnergy() - $this->ReadPropertyFloat('StartEnergy')));
      IPS_SetProperty($this->InstanceID, 'StartTime', 0);
      IPS_ApplyChanges($this->InstanceID);
    }

    SetValueInteger($this->GetIDForIdent('STATE'), $value);
  }

  public function GetState() {
    return GetValueInteger($this->GetIDForIdent('STATE'));
  }

  /* Timer Off */

  // Prüfe ob der Timer schon läuft. Wenn nicht, starte den Timer.
  public function StartTimerOff() {
    if(!$this->ReadPropertyBoolean('TimerOff')) {
      IPS_SetProperty($this->InstanceID, 'TimerOff', true);
      $this->SetTimerInterval('TimerOff', $this->ReadPropertyInteger('DelayOff') * 1000);
    }
  }
  public function StopTimerOff() {
    IPS_SetProperty($this->InstanceID, 'TimerOff', false);
    $this->SetTimerInterval('TimerOff', 0);
  }

  /* Timer Done */

  // Prüfe ob der Timer schon läuft. Wenn nicht, starte den Timer.
  public function StartTimerDone() {
    if(!$this->ReadPropertyBoolean('TimerDone')) {
      IPS_SetProperty($this->InstanceID, 'TimerDone', true);
      $this->SetTimerInterval('TimerDone', $this->ReadPropertyInteger('DelayDone') * 1000);
    }
  }
  public function StopTimerDone() {
    IPS_SetProperty($this->InstanceID, 'TimerDone', false);
    $this->SetTimerInterval('TimerDone', 0);
  }

}
