<?php

/**
 * VisualisationHelper.php
 *
 * Part of the Trait-Libraray for IP-Symcon Modules.
 *
 * @package       traits
 * @author        Heiko Wilknitz <heiko@wilkware.de>
 * @copyright     2021 Heiko Wilknitz
 * @link          https://wilkware.de
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

declare(strict_types=1);

/**
 * Helper class for support new tile visu.
 */
trait VisualisationHelper
{
    /**
     * Build a html widget for waste dates.
     *
     * @param array $waste Array with waste names and the next pick-up date.
     */
    protected function BuildWidget(array $waste, string $skin)
    {
        $this->SendDebug(__FUNCTION__, $waste);
        // (0) tabel with all infos
        $table = [];
        // (1) build new data array
        foreach ($waste as $key => $value) {
            $id = @$this->GetIDForIdent($value['ident']);
            if ($id !== false) {
                $name = IPS_GetName($id);
                $type = $this->RecognizeWaste($name);
                $date = $value['date'];
                $days = $this->CalcDaysToDate($date);
                $table[] = ['name' => $name, 'type' => $type, 'date' => $date, 'days' => $days];
            }
        }
        // (2) sort waste by date
        usort($table, function ($a, $b)
        {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        // (3) build html texts
        $next = '';
        // show today only if no date tommorow
        if (strtotime($table[0]['date']) === strtotime('today')) {
            $next = $this->Translate('Heute');
        }
        // tommorow overrule today
        if (strtotime($table[0]['date']) === strtotime('tomorrow')) {
            $next = $this->Translate('Morgen');
        }
        // generate widget for tile visu
        if ($next == '') {
            $next = date('d.m.', strtotime($table[0]['date']));
            $next = $this->Translate(date('D', strtotime($table[0]['date']))) . '. ' . $next;
        }
        $textS = '';
        $textM = '';
        $textL = '';
        // date infos
        $days = $table[0]['days'];
        $day = strtotime($table[0]['date']);
        $wd = $this->Translate(date('l'));
        $sd = date('d.m.', $day);
        $wn = $table[0]['name'];
        if ($days > 1) {
            $textS = "in $days " . $this->Translate('days');
            $textM = "$wn<br /><br />" . $this->Translate('Next pickup:') . "<br />in $days " . $this->Translate('days') . '<br />' . $this->Translate('on') . " $wd $sd";
        } else {
            $textS = $next;
            $textM = "$wn<br /><br />" . $this->Translate('Next pickup:') . "<br />$next<br />" . $this->Translate('on') . " $wd $sd";
        }
        // table rows
        $textL = '';
        foreach ($table as $row) {
            if ($row['days'] == 0) $text = $this->Translate('Today');
            if ($row['days'] == 1) $text = $this->Translate('Tomorrow');
            if ($row['days'] >= 2) $text = $row['days'] . ' ' . $this->Translate('days');
            $textL .= '<tr>';
            $textL .= '<td><svg class="icon icon--' . $row['type'] . '" aria-hidden="true"><use xlink:href="#icon-waste" href="#icon-waste" /></svg></td>';
            $textL .= '<td>' . $row['name'] . '</td>';
            $textL .= '<td>' . $row['date'] . '</td>';
            $textL .= '<td><span class="badge">' . $text . '</span></td>';
            $textL .= '</tr>';
        }
        // (4) assamble cards
        $removal = $this->Translate('Removal');
        $pickup = $this->Translate('Pickup');
        $date = $this->Translate('Date');
        $tbc = ($skin == 'light') ? '#D7D6D6' : '#4A4B4D';
        $html = '
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    body {margin: 0px;}
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: transparent; border-radius: 20px; }
    ::-webkit-scrollbar-thumb:hover { background: #555; }
    .cardS { display:block; }
    .cardM { display:none; }
    .cardL { display:none; }
    #grid {width: 100%; height: 100%; display: grid; justify-items: center; }
    #grid > div { justify-content: center; align-items: center; display: flex; width: 100%; }
    .icon {width: 100%; height: 100%; }
    .icon--green {fill:  #58A906;}
    .icon--yellow { fill: #FFC107;}
    .icon--gray { fill: #999A9C;}
    .icon--blue { fill: #11A0F3;}
    .icon--red { fill: #F35A2C;}
    .text { font-size: 1.2em; }
    .hidden { display: none; }
    table.wwx { border-collapse: collapse; width: 100% }
    .wwx th, .wwx td { vertical-align: middle; text-align: left; padding: 10px; }
    .wwx tr { border-bottom: 2px solid ' . $tbc . '; }
    .tr4 tr > :nth-child(4) { text-align:right; }
    .badge { background-color: #00CDAB; font-weight: bold; font-size: 80%; border-radius: 10em; min-width: 1.5em; padding: 0.5em; text-align: center; }
    @media screen and (min-width: 384px) {
        .cardS { display:none; }
        .cardM { display:block; }
        .cardL { display:none; }
        #grid { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr; }
    }
    @media screen and (min-width: 600px) {
        .cardS { display:none; }
        .cardM { display:none; }
        .cardL { display:block; }
        .icon { width: 24px; height: 24px; }
    }
</style>
<!-- Small Cards -->
<div class="cardS">
    <div id="grid">
        <svg class="icon icon--' . $table[0]['type'] . '" aria-hidden="true"><use xlink:href="#icon-waste" href="#icon-waste" /></svg>
        <div class="text">' . $textS . '</div>
    </div>
</div>
<!-- Medium Cards -->
<div class="cardM">
    <div id="grid">
        <svg class="icon icon--' . $table[0]['type'] . '" aria-hidden="true"><use xlink:href="#icon-waste" href="#icon-waste" /></svg>
        <div class="text">' . $textM . '</div>
    </div>
</div>
<!-- Large Cards -->
<div class="cardL">
    <table class="wwx tr4">
        <thead >
            <tr><th></th><th>' . $removal . '</th><th>' . $date . '</th><th>' . $pickup . '</th></tr>
        </thead>' .
        $textL . '
    </table>
</div>

<!-- Hidden Inline SVG -->
<svg xmlns="http://www.w3.org/2000/svg" class="hidden">
    <symbol id="icon-waste" viewBox="0 0 24 24">
        <path d="M3 6.38597C3 5.90152 3.34538 5.50879 3.77143 5.50879L6.43567 5.50832C6.96502 5.49306 7.43202 5.11033 7.61214 4.54412C7.61688 4.52923 7.62232 4.51087 7.64185 4.44424L7.75665 4.05256C7.8269 3.81241 7.8881 3.60318 7.97375 3.41617C8.31209 2.67736 8.93808 2.16432 9.66147 2.03297C9.84457 1.99972 10.0385 1.99986 10.2611 2.00002H13.7391C13.9617 1.99986 14.1556 1.99972 14.3387 2.03297C15.0621 2.16432 15.6881 2.67736 16.0264 3.41617C16.1121 3.60318 16.1733 3.81241 16.2435 4.05256L16.3583 4.44424C16.3778 4.51087 16.3833 4.52923 16.388 4.54412C16.5682 5.11033 17.1278 5.49353 17.6571 5.50879H20.2286C20.6546 5.50879 21 5.90152 21 6.38597C21 6.87043 20.6546 7.26316 20.2286 7.26316H3.77143C3.34538 7.26316 3 6.87043 3 6.38597Z"/>
        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.5956 22.0001H12.4044C15.1871 22.0001 16.5785 22.0001 17.4831 21.1142C18.3878 20.2283 18.4803 18.7751 18.6654 15.8686L18.9321 11.6807C19.0326 10.1037 19.0828 9.31524 18.6289 8.81558C18.1751 8.31592 17.4087 8.31592 15.876 8.31592H8.12404C6.59127 8.31592 5.82488 8.31592 5.37105 8.81558C4.91722 9.31524 4.96744 10.1037 5.06788 11.6807L5.33459 15.8686C5.5197 18.7751 5.61225 20.2283 6.51689 21.1142C7.42153 22.0001 8.81289 22.0001 11.5956 22.0001ZM10.2463 12.1886C10.2051 11.7548 9.83753 11.4382 9.42537 11.4816C9.01321 11.525 8.71251 11.9119 8.75372 12.3457L9.25372 17.6089C9.29494 18.0427 9.66247 18.3593 10.0746 18.3159C10.4868 18.2725 10.7875 17.8856 10.7463 17.4518L10.2463 12.1886ZM14.5746 11.4816C14.9868 11.525 15.2875 11.9119 15.2463 12.3457L14.7463 17.6089C14.7051 18.0427 14.3375 18.3593 13.9254 18.3159C13.5132 18.2725 13.2125 17.8856 13.2537 17.4518L13.7537 12.1886C13.7949 11.7548 14.1625 11.4382 14.5746 11.4816Z"/>
    </symbol>
</svg>
';
        $this->SetValueString('Widget', $html);
    }

    /**
     * Calculate days up to a date
     *
     * @param mixed $startDate
     * @param null $endDate
     * @return int Number of days
     */
    private function CalcDaysToDate($startDate, $endDate = null)
    {
        if (empty($endDate)) $endDate = date('Y-m-d');
        return round(abs(strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24));
    }

    /**
     * Recognize waste type for given name
     *
     * @param mixed $name
     */
    private function RecognizeWaste($name)
    {
        if (preg_match('/(papier|pappe|zeitung)/i', $name)) {
            return 'blue';
        }
        if (preg_match('/(bio|grün|garten|baum|schnittgut)/i', $name)) {
            return 'green';
        }
        if (preg_match('/(gelb|plast|pvc)/i', $name)) {
            return 'yellow';
        }
        if (preg_match('/(schadstoff|sonder|sperr)/i', $name)) {
            return 'red';
        }
        // Rest or all others
        return 'gray';
    }
}
