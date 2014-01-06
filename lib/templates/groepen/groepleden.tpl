{if $actie=='pasfotos'}
	<div class="pasfotomatrix">
		{foreach from=$groep->getLidObjects() item=groeplid name=pasfotos}
			{if $smarty.foreach.pasfotos.index==20}
				<a class="toonmeer handje" onclick="toggleDiv('meerLeden-{$groep->getId()}'); this.parentNode.removeChild(this)">
					Nog {$smarty.foreach.pasfotos.total-20} leden tonen...
				</a>
				<div class="verborgen" id="meerLeden-{$groep->getId()}">
				{assign var='meerisopen' value='true'}
			{/if}

			<div class="pasfoto">{$groeplid->getNaamLink('pasfoto', 'link')}</div>

			{if $smarty.foreach.pasfotos.last AND $meerisopen}
				</div>
			{/if}
		{/foreach}
	</div>
{else}

	<table class="leden">
		{foreach from=$groep->getLeden() item=groeplid}
			<tr>
				<td>{$groeplid.uid|csrnaam:'civitas'}</td>
				{if $groep->magBewerken() OR ($loginlid->getUid()==$groeplid.uid AND ($groep->getToonFuncties()=='tonen' OR $groep->getToonFuncties()=='verbergen'))}
					<td id="bewerk_{$groep->getId()}|{$groeplid.uid}" class="inline_edit">
						<span class="text">
							{foreach from=$groeplid.functie item=glfunctie name=glfunctie}
								{if $smarty.foreach.glfunctie.iteration > 1} - {/if}{$glfunctie|escape:'html'}
							{/foreach}
						</span>
						{if $groep->hasFunctiefilter()}
							{foreach from=$groep->getFunctiefilters() item=filter name=filter}
								<select name="functie[]" class="editbox" id="functie_input_{$groep->getId()}{$groeplid.uid}">
									{foreach from=$filter item=filteroption}
										<option value="{$filteroption|escape:'html'}" {if $filteroption==$groeplid.functie[$smarty.foreach.filter.index]}selected="selected"{/if}>{$filteroption|escape:'html'}</option>
									{/foreach}
								</select>
							{/foreach}
						{else}
							<input type="text" maxlength="25" 
								value="{foreach from=$groeplid.functie item=glfunctie name=glfunctie}{if $smarty.foreach.glfunctie.iteration > 1} - {/if}{$glfunctie|escape:'html'}{/foreach}"
								class="editbox"  />
						{/if}
					</td>
				{else}	
					{if $groep->toonFuncties()}
						<td><em>
							{foreach from=$groeplid.functie item=glfunctie name=glfunctie}
								{if $smarty.foreach.glfunctie.iteration > 1} - {/if}{$glfunctie|escape:'html'}
							{/foreach}
						</em></td>
					{/if}
				{/if}
				{if $groep->magBewerken() OR $loginlid->getUid()==$groeplid.uid}
					<td>
					{assign var=type value=$groep->getType()}
					{if in_array($groep->getTypeId(), array(2, 3)) AND $groep->getStatus()=='ht'}{* maak lid ot voor huizen/onderverenigingen. Dit kunnen leden ook bij zichzelf doen. *}
						<a href="/actueel/groepen/{$type->getNaam()}/{$groep->getId()}/maakLidOt/{$groeplid.uid}" title="Verplaats lid naar o.t.-groep" 
							{if !$groep->isAdmin()}onclick="return confirm('Weet u zeker dat u deze bewoner naar de oudbewonersgroep wilt verplaatsen?')"{/if}>
							&raquo;
						</a>
					{/if}
					{if $groep->isAdmin() OR $groep->isEigenaar() OR $groeplid.uid!=$loginlid->getUid()} {* We kunnen onzelf niet uit een groep gooien gooien *}
						<a href="/actueel/groepen/{$type->getNaam()}/{$groep->getId()}/verwijderLid/{$groeplid.uid}" title="Verwijder lid uit groep">X</a>
					{/if}
					</td>
				{/if}
			</tr>
		{/foreach}
	</table>
{/if}
{* We geven nog even even een aanmeldding weer als de groep aanmeldbaar is. *}
{if $groep->isAanmeldbaar() AND !$groep->isLid() AND $loginlid->hasPermission('P_LOGGED_IN')}
	<div class="aanmelden">
		{if $groep->magAanmelden()}
			{assign var=type value=$groep->getType()}
			{if $groep->getToonFuncties()=='niet' OR $groep->getToonFuncties()=='tonenzonderinvoer'}
				<a  {if $actie!='pasfotos'}class="knop"{/if} href="/actueel/groepen/{$type->getNaam()}/{$groep->getId()}/aanmelden" onclick="return confirm('Weet u zeker dat u zich wilt aanmelden?')">
					{if $actie=='pasfotos'}
						<img class="pasfoto" src="{$csr_pics}/groepen/aanmelden.jpg" title="Aanmelden voor deze groep"
							onmouseover="this.src='/tools/pasfotos.php?image';" onmouseout="this.src='{$csr_pics}/groepen/aanmelden.jpg';" />
					{else}
						Aanmelden voor deze groep
					{/if}
				</a>
				{if $groep->getVrijeplaatsen()!=0}<br />{/if}{* nog-vrije-plaatsen-melding *}
			{else}
				<form action="/actueel/groepen/{$type->getNaam()}/{$groep->getId()}/aanmelden" method="post" id="aanmeldForm" class="clear">
					<strong>Aanmelden</strong><br />
					{if $groep->hasFunctiefilter()}
						{foreach from=$groep->getFunctiefilters() item=filter}
							<select name="functie[]">
								{foreach from=$filter item=filteroption}
									<option value="{$filteroption|escape:'html'}">{$filteroption|escape:'html'}</option>
								{/foreach}
							</select>
						{/foreach}
					{else}
						<input type="text" name="functie" maxlength="60" class="functie" />
					{/if}&nbsp;<input type="submit" value="aanmelden" />
				</form>
				
			{/if}
			{if $groep->getVrijeplaatsen()!=0}nog {$groep->getVrijeplaatsen()} plaatsen vrij{/if}
		{elseif $groep->isVol()}
			Deze groep is vol, u kunt zich niet meer aanmelden.
		{/if}
	</div>
{/if}
