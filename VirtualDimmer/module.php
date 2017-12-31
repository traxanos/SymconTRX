<?
class VirtualDimmer extends IPSModule {

  public function __construct($InstanceID) {
    parent::__construct($InstanceID);
  }

  public function Create() {
    parent::Create();

    $this->RegisterPropertyInteger('Quantity', 1);
    $this->RegisterPropertyInteger('Min', 0);
    $this->RegisterPropertyInteger('Max', 100);
    $this->RegisterPropertyInteger('Step', 10);
    $this->RegisterPropertyBoolean('ToggleLast', false);
    $this->RegisterPropertyInteger('Reference', 0);

    for($i = 1; $i <= 99; $i++) {
      $this->RegisterPropertyInteger("ButtonShort$i", 0);
      $this->RegisterPropertyInteger("ButtonLong$i", 0);
      $this->RegisterPropertyInteger("ButtonHold$i", 0);
      $this->RegisterPropertyInteger("ButtonRelease$i", 0);
    }
  }

  public function GetConfigurationForm() {
    $form = Array(
      'elements' => Array(),
      'actions' => Array()
    );

    $form['elements'][] = Array('type' => 'Label', 'label' => "Dimmer");
    $form['elements'][] = Array('type' => 'NumberSpinner', 'name' => 'Min', 'caption' => 'Min');
    $form['elements'][] = Array('type' => 'NumberSpinner', 'name' => 'Max', 'caption' => 'Max');
    $form['elements'][] = Array('type' => 'NumberSpinner', 'name' => 'Step', 'caption' => 'Schritte');

    $quantity = $this->ReadPropertyInteger('Quantity');
    $form['elements'][] = Array('type' => 'NumberSpinner', 'name' => 'Quantity', 'caption' => 'Anzahl Taster');
    for($i = 1; $i <= $quantity; $i++) {
      $form['elements'][] = Array('type' => 'Label', 'label' => "Taster $i");
      $form['elements'][] = Array('type' => 'SelectVariable', 'name' => "ButtonShort$i", 'caption' => 'Press short *');
      $form['elements'][] = Array('type' => 'SelectVariable', 'name' => "ButtonLong$i", 'caption' => 'Press long');
      $form['elements'][] = Array('type' => 'SelectVariable', 'name' => "ButtonHold$i", 'caption' => 'Hold long');
      $form['elements'][] = Array('type' => 'SelectVariable', 'name' => "ButtonRelease$i", 'caption' => 'Release long');
    }

    $form['elements'][] = Array('type' => 'Label', 'label' => "Optionen");
    $form['elements'][] = Array('type' => 'CheckBox', 'name' => 'ToggleLast', 'caption' => 'Soll beim toggeln der letzte statt der maximal Wert genutzt werden?');

    $form['elements'][] = Array('type' => 'Label', 'label' => "Eine Referenz um den aktuell Wert zurück zu lesen");
    $form['elements'][] = Array('type' => 'SelectVariable', 'name' => 'Reference', 'caption' => 'Referenz *');

    $form['elements'][] = Array('type' => 'Label', 'label' => "* Optional");
    return json_encode($form);
  }

  public function ApplyChanges() {
    parent::ApplyChanges();

    if (!@$this->GetIDForIdent('CURRENT')) $this->RegisterVariableInteger('CURRENT', 'Current', '', 1);
    if (!@$this->GetIDForIdent('LAST')) $this->RegisterVariableInteger('LAST', 'Last', '', 2);
    if (!@$this->GetIDForIdent('DIRECTION')) $this->RegisterVariableBoolean('DIRECTION', 'Richtung', '', 3);

    if (!$ActionID = @$this->GetIDForIdent('ACTION')) {
      $ActionID = IPS_CreateScript(0);
      IPS_SetParent($ActionID, $this->InstanceID);
      IPS_SetIdent($ActionID, 'ACTION');
      IPS_SetHidden($ActionID, true);
      IPS_SetName($ActionID, 'Aktion');
      IPS_SetPosition($ActionID, 100);
    }

    $quantity = $this->ReadPropertyInteger('Quantity');
    for($i = 1; $i <= 99; $i++) {
      $this->ApplyEventHandler("ButtonShort$i", "EVENT_SHORT_$i", "Button $i - Event press short", 'PressShort');
      $this->ApplyEventHandler("ButtonLong$i", "EVENT_LONG_$i", "Button $i - Event press long", 'PressLong');
      $this->ApplyEventHandler("ButtonHold$i", "EVENT_HOLD_$i", "Button $i - Event hold long", 'HoldLong');
      $this->ApplyEventHandler("ButtonRelease$i", "EVENT_RELEASE_$i", "Button $i - Event release long", 'ReleaseLong');
    }
  }

  private function SyncReference() {
    if($ReferenceID = $this->ReadPropertyInteger('Reference')) {
      $min = $this->ReadPropertyInteger('Min');
      $max = $this->ReadPropertyInteger('Max');
      $current = GetValueInteger($this->GetIDForIdent('CURRENT'));
      $direction = GetValueBoolean($this->GetIDForIdent('DIRECTION'));

      $value = GetValue($ReferenceID);
      if($value === true) {
        $current = $max;
        $direction = true;
      } elseif($value === false) {
        $current = $min;
        $direction = false;
      } else {
        $current = (integer)$value;
      }

      SetValueBoolean($this->GetIDForIdent('DIRECTION'), $direction);
      SetValueInteger($this->GetIDForIdent('CURRENT'), $current);
    }
  }

  private function ApplyEventHandler($ButtonName, $EventIdent, $EventTitle, $fn) {
    if($ButtonID = @$this->ReadPropertyInteger($ButtonName)) {
      if (!$EventID = @IPS_GetObjectIDByIdent($EventIdent, $this->InstanceID)) {
        $EventID = IPS_CreateEvent(0);
        IPS_SetParent($EventID, $this->InstanceID);
        IPS_SetIdent($EventID, $EventIdent);
        IPS_SetHidden($EventID, true);
        IPS_SetName($EventID, $EventTitle);
        IPS_SetPosition($EventID, 1000);
      }
      IPS_SetEventTrigger($EventID, 0, $ButtonID);
      IPS_SetEventScript($EventID, "VD_$fn(\$_IPS['TARGET']);");
      IPS_SetEventActive($EventID, true);
    } else {
      if($EventID = @$this->GetIDForIdent($EventIdent)) IPS_DeleteEvent($EventID);
    }
  }

  public function PressShort() {
    $this->SyncReference();
    $min = $this->ReadPropertyInteger('Min');
    $max = $this->ReadPropertyInteger('Max');
    $current = GetValueInteger($this->GetIDForIdent('CURRENT'));
    $last = GetValueInteger($this->GetIDForIdent('LAST'));
    $direction = GetValueBoolean($this->GetIDForIdent('DIRECTION'));

    if($current > $min) {
      $current = $min;
      $direction = false;
    } else {
      $togglelast = $this->ReadPropertyBoolean('ToggleLast');
      if ($togglelast && $last > 0) {
        $current = $last;
      } else {
        $current = $max;
      }
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
    $this->SyncReference();
    $this->RunDimmer();
  }

  public function HoldLong() {
    $this->RunDimmer();
  }

  public function ReleaseLong() {
    $direction = !GetValueBoolean($this->GetIDForIdent('DIRECTION'));
    SetValueBoolean($this->GetIDForIdent('DIRECTION'), $direction);
  }

  private function RunDimmer() {
    $direction = GetValueBoolean($this->GetIDForIdent('DIRECTION'));
    $current = GetValueInteger($this->GetIDForIdent('CURRENT'));
    $before = $current;

    $min = $this->ReadPropertyInteger('Min');
    $max = $this->ReadPropertyInteger('Max');
    $step = $this->ReadPropertyInteger('Step');

    if ($direction) { // True -> Down
      $current -= $step;
    } else { // False -> Up
      $current += $step;
    }

    if($current >= $max) {
      $current = $max;
    } elseif($current <= $min) {
      $current = $min;
    }

    if($before != $current) {
      SetValueInteger($this->GetIDForIdent('CURRENT'), $current);
      SetValueInteger($this->GetIDForIdent('LAST'), $current);
      $this->CallAction($current);
    }
  }
}
