<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-list-alt"></i> {{Général}}</legend>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Adresse IP de la box}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="tydom::host" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Adresse mac de la box}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="tydom::mac" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Nom d'utilisateur}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="tydom::login" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Mot de passe}}</label>
            <div class="col-sm-2">
                <input type="password" class="configKey form-control" data-l1key="tydom::password" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Acces distant}}</label>
            <div class="col-sm-2">
                <input type="checkbox" class="configKey" data-l1key="tydom::remote" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Port Serveur}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="tydom::portServer" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Fréquence Polling (min)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="tydom::freqPolling" />
            </div>
        </div>        
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Délai mise à jour (s)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="tydom::delaiUpdateData" />
            </div>
        </div>
    </fieldset>
</form>

