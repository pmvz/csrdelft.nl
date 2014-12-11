{getMelding()}

{$zoekform->view()}

{if LoginModel::mag('P_ADMIN')}
	<div class="forumheadbtn">
		<a href="/forum/aanmaken" class="btn round post popup confirm" title="Deelforum aanmaken">{icon get="add"} </a>
	</div>
{/if}

{include file='MVC/forum/head_buttons.tpl'}

<h1>Forum{include file='MVC/forum/rss_link.tpl'}</h1>

<table id="forumtabel">
	{foreach from=$categorien item=cat}
		<thead>
			<tr>
				<th>
					<a name="{$cat->categorie_id}">{$cat->titel}</a>
					<span class="forumcategorie-omschrijving">{$cat->omschrijving}</span>
				</th>
				<th>Onderwerpen</th>
				<th>Berichten</th>
				<th>Recente wijziging</th>
			</tr>
		</thead>
		<tbody>
			{if !$cat->hasForumDelen()}
				<tr>
					<td colspan="4">Deze categorie is leeg.</td>
				</tr>
			{/if}
			{foreach from=$cat->getForumDelen() item=deel}
				{include file='MVC/forum/deel_lijst.tpl'}
			{/foreach}
		</tbody>
	{/foreach}
</table>