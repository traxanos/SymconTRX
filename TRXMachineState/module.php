<?
class TRXMachineState extends IPSModule {
  public function __construct($InstanceID) {
    parent::__construct($InstanceID);
  }

  public function Create() {
    if (!IPS_VariableProfileExists('TRXMS.State')) {
      IPS_CreateVariableProfile('TRXMS.State', 1);
      IPS_SetVariableProfileAssociation('TRXMS.State', 0, 'Off', '', 0x000000);
      IPS_SetVariableProfileAssociation('TRXMS.State', 1, 'On', '', 0x000000);
      IPS_SetVariableProfileAssociation('TRXMS.State', 2, 'Running', '', 0x000000);
      IPS_SetVariableProfileAssociation('TRXMS.State', 3, 'Done', '', 0x000000);
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
    $this->RegisterPropertyInteger('StartEnergy', 0);

    $this->RegisterTimer('TimerOff', 0, 'TRXMS_SetState($_IPS[\'TARGET\'], 0);');
    $this->RegisterTimer('TimerDone', 0, 'TRXMS_SetState($_IPS[\'TARGET\'], 4);');

    $this->SetState(0);
  }

  public function ApplyChanges() {
    parent::ApplyChanges();
    if (!@$this->GetIDForIdent('STATE')) $this->RegisterVariableInteger('STATE', 'Zustand', 'TRXMS.State', 1);
    if (!@$this->GetIDForIdent('ENERGY')) $this->RegisterVariableInteger('ENERGY', 'Verbrauch', '', 2);
    if (!@$this->GetIDForIdent('DURATION')) $this->RegisterVariableInteger('DURATION', 'Dauer', '', 3);

    $PowerID = $this->ReadPropertyInteger('PowerId');

    if (!$EventID = @IPS_GetObjectIDByIdent("ON_POWER_CHANGE", $this->InstanceID)) {
      $EventID = IPS_CreateEvent(0);
      IPS_SetParent($EventID, $this->InstanceID);
      IPS_SetIdent($EventID, 'ON_POWER_CHANGE');
      IPS_SetHidden($EventID, true);
      IPS_SetName($EventID, 'On power change');
      IPS_SetPosition($EventID, 999);
    }
    IPS_SetEventTrigger($EventID, 0, $PowerID);
    IPS_SetEventScript($EventID, 'TRXMS_Update($_IPS[\'TARGET\']);');
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
      SetValueInteger($this->GetIDForIdent('DURATION'), (time() - $this->ReadPropertyInteger('StartTime')));
      SetValueInteger($this->GetIDForIdent('ENERGY'), ($this->CurrentEnergy() - $this->ReadPropertyInteger('StartEnergy')));
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
