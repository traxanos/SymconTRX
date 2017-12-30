<?
class VirtualDimmer extends IPSModule {
  public function __construct($InstanceID) {
    parent::__construct($InstanceID);
  }

  public function Create() {
    parent::Create();

    $this->RegisterPropertyInteger('ButtonShort', 0);
    $this->RegisterPropertyInteger('ButtonLong', 0);
    $this->RegisterPropertyInteger('ButtonHold', 0);
    $this->RegisterPropertyInteger('ButtonRelease', 0);
    $this->RegisterPropertyInteger('ValueStart', 0);
    $this->RegisterPropertyInteger('ValueEnd', 100);
    $this->RegisterPropertyInteger('ValueStep', 10);
  }

  public function ApplyChanges() {
    parent::ApplyChanges();

    if (!@$this->GetIDForIdent('CURRENT')) $this->RegisterVariableInteger('CURRENT', 'Current', '', 1);
    if (!@$this->GetIDForIdent('DIRECTION')) $this->RegisterVariableBoolean('DIRECTION', 'Richtung', '', 2);

    if (!$ActionID = @$this->GetIDForIdent('ACTION')) {
      $ActionID = IPS_CreateScript(0);
      IPS_SetParent($ActionID, $this->InstanceID);
      IPS_SetIdent($ActionID, 'ACTION');
      IPS_SetHidden($ActionID, true);
      IPS_SetName($ActionID, 'Aktion');
    }

    $this->ApplyEventHandler('ButtonShort', 'EVENT_SHORT', 'Event press short', 'PressShort');
    $this->ApplyEventHandler('ButtonLong', 'EVENT_LONG', 'Event press long', 'PressLong');
    $this->ApplyEventHandler('ButtonHold', 'EVENT_HOLD', 'Event hold long', 'HoldLong');
    $this->ApplyEventHandler('ButtonRelease', 'EVENT_RELEASE', 'Event release long', 'ReleaseLong');
  }

  private function ApplyEventHandler($ButtonName, $EventIdent, $EventTitle, $fn) {
    if($ButtonID = @$this->ReadPropertyInteger($ButtonName)) {
      if (!$EventID = @IPS_GetObjectIDByIdent($EventIdent, $this->InstanceID)) {
        $EventID = IPS_CreateEvent(0);
        IPS_SetParent($EventID, $this->InstanceID);
        IPS_SetIdent($EventID, $EventIdent);
        IPS_SetHidden($EventID, true);
        IPS_SetName($EventID, $EventTitle);
      }
      IPS_SetEventTrigger($EventID, 0, $ButtonID);
      IPS_SetEventScript($EventID, "VD_$fn(\$_IPS['TARGET']);");
      IPS_SetEventActive($EventID, true);
    } else {
      if($EventID = @$this->GetIDForIdent($EventIdent)) IPS_DeleteEvent($EventID);
    }
  }

  public function PressShort() {
    //IPS_LogMessage('VirtualDimmer', "Press short");
    $start = $this->ReadPropertyInteger('ValueStart');
    $end = $this->ReadPropertyInteger('ValueEnd');
    $current = GetValueInteger($this->GetIDForIdent('CURRENT'));
    if($current > $start) {
      $current = $start;
      $direction = false;
    } else {
      $current = $end;
      $direction = true;
    }
    $this->CallAction($current);

    SetValueBoolean($this->GetIDForIdent('DIRECTION'), $direction);
    SetValueInteger($this->GetIDForIdent('CURRENT'), $current);
  }

  private function CallAction($value) {
    if ($ActionID = @$this->GetIDForIdent('ACTION')) {
      IPS_RunScriptEx($ActionID, Array("VALUE" => $value));
    }
  }

  public function PressLong() {
    //IPS_LogMessage('VirtualDimmer', "Press long");
    $this->RunDimmer();
  }

  public function HoldLong() {
    //IPS_LogMessage('VirtualDimmer', "Hold long");
    $this->RunDimmer();
  }

  public function ReleaseLong() {
    //IPS_LogMessage('VirtualDimmer', "Release long");
    $current = GetValueInteger($this->GetIDForIdent('CURRENT'));
    $direction = GetValueBoolean($this->GetIDForIdent('DIRECTION'));
    $start = $this->ReadPropertyInteger('ValueStart');
    $end = $this->ReadPropertyInteger('ValueEnd');

    $direction = !$direction;

    SetValueBoolean($this->GetIDForIdent('DIRECTION'), $direction);
  }

  private function RunDimmer() {
    $current = GetValueInteger($this->GetIDForIdent('CURRENT'));
    $before = $current;
    $start = $this->ReadPropertyInteger('ValueStart');
    $end = $this->ReadPropertyInteger('ValueEnd');
    $step = $this->ReadPropertyInteger('ValueStep');
    $direction = GetValueBoolean($this->GetIDForIdent('DIRECTION'));

    if ($direction) { // True -> Down
      $current -= $step;
    } else { // False -> Up
      $current += $step;
    }

    if($current >= $end) {
      $current = $end;
    } elseif($current <= $start) {
      $current = $start;
    }

    if($before != $current) {
      SetValueInteger($this->GetIDForIdent('CURRENT'), $current);
      $this->CallAction($current);
    }
  }
}
