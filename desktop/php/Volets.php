<?php
if (!isConnect('admin')) {
throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'Volets');
$eqLogics = eqLogic::byType('Volets');
?>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCqFW26gzrAcgO7C2mKNr2A9Y76rd8pSQ8"></script>
<div class="row row-overflow">
	<div class="col-lg-2">
		<div class="bs-sidebar">
			<ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
				<a class="btn btn-default eqLogicAction" style="width : 50%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter}}</a>
				<li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
				<?php
					foreach ($eqLogics as $eqLogic) 
						echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
				?>
			</ul>
		</div>
	</div>
	<div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
		<legend>{{Mes Zones}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
				<center>
					<i class="fa fa-plus-circle" style="font-size : 7em;color:#94ca02;"></i>
				</center>
				<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;;color:#94ca02"><center>Ajouter</center></span>
			</div>
			<?php
				foreach ($eqLogics as $eqLogic) {
					$opacity = '';
					if ($eqLogic->getIsEnable() != 1) {
						$opacity = '
						-webkit-filter: grayscale(100%);
						-moz-filter: grayscale(100);
						-o-filter: grayscale(100%);
						-ms-filter: grayscale(100%);
						filter: grayscale(100%); opacity: 0.35;';
					}
					echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
					echo "<center>";
					echo '<img src="plugins/Volets/doc/images/Volets_icon.png" height="105" width="95" />';
					echo "</center>";
					echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
					echo '</div>';
				}
			?>
		</div>
	</div>  
	<div class="col-lg-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
		<form class="form-horizontal">
			<fieldset>
				<legend><i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Configuration des zones et actions}}<i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i></legend>
			</fieldset> 
		</form>
		<div class="row" style="padding-left:25px;">
			<ul class="nav nav-tabs" id="tab_zones">	
				<li><a class="btn cmdAction" data-action="add"><i class="fa fa-plus-circle"></i>{{Ajouter}}</a></li>
				<li class="active"><a href="#tab_general"><i class="fa fa-cogs"></i> {{Général}}</a></li>
				<li><a href="#tab_map"><i class="fa fa-map"></i> {{Afficher la carte}}</a></li>
				<li><a href="#tab_parametre"><i class="fa fa-pencil"></i> {{Paramètres}}</a></li>	
			</ul>
			<div class="tab-content TabCmdZone">
				<div class="tab-pane" id="tab_general">
					<form class="form-horizontal">
						<fieldset>
							<div class="form-group">
								<label class="col-sm-2 control-label">{{Nom de la Zone}}</label>
								<div class="col-sm-9">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement Direct Energie}}"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-5 control-label" >{{Objet parent}}</label>
								<div class="col-sm-9">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
											foreach (object::all() as $object) 
												echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-5 control-label" ></label>
								<div class="col-sm-9">
									<label>{{Activer}}</label>
									<input type="checkbox" class="eqLogicAttr" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>
									<label>{{Visible}}</label>
									<input type="checkbox" class="eqLogicAttr" data-label-text="{{Visible}}" data-l1key="isVisible" checked/>
								</div>
							</div>
						</fieldset>
					</form>
				</div>	
				<div class="tab-pane" id="tab_map">
					<form class="form-horizontal">
						<fieldset>
							<div id="map" style="width:75%;height:75%"></div>
						</fieldset>
					</form>	
				</div>	
				<div class="tab-pane" id="tab_parametre">
					<form class="form-horizontal">
						<fieldset>
							<div class="form-group">
								<legend>{{Configuration}} </legend>
								<div class="form-group">
									<label class="col-lg-5 control-label">{{Héliotrope}}</label>
									<div class="col-sm-9">
										<select class="eqLogicAttr" data-l1key="configuration" data-l2key="heliotrope">
											<option>Aucun</option>
											<?php
												foreach(eqLogic::byType('heliotrope') as $heliotrope)
													echo '<option value="'.$heliotrope->getId().'">'.$heliotrope->getName().'</option>';
											?>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-5 control-label">{{Activation de la gestion par température}}</label>
									<div class="col-sm-9">
										<input type="checkbox" class="eqLogicAttr" data-label-text="{{Activer}}" data-l1key="configuration" data-l2key="EnableTemp"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-5 control-label">{{Activation de la gestion jours nuit}}</label>
									<div class="col-sm-9">
										<input type="checkbox" class="eqLogicAttr" data-label-text="{{Activer}}" data-l1key="configuration" data-l2key="EnableNight"/>
									</div>
								</div>									
								<div class="form-group">
									<label class="col-lg-5 control-label">{{Delais apres le levée du jours}}</label>
									<div class="col-sm-9">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="DelaisDay" placeholder="{{Delais apres le levée du jours}}"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-lg-5 control-label">{{Delais avant la tombée de la nuit}}</label>
									<div class="col-sm-9">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="DelaisNight" placeholder="{{Delais avant la tombée de la nuit}}"/>
									</div>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>
		</div>
		<form class="form-horizontal">
			<fieldset>
				<div class="form-actions">
					<a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
					<a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
				</div>
			</fieldset>
		</form>
	</div>
</div>

<?php include_file('desktop', 'Volets', 'js', 'Volets'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
