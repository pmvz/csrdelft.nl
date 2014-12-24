{getMelding()}

{$zoekform->view()}

{if LoginModel::mag('P_ADMIN')}
	<div class="forumheadbtn">
		<a href="/forum/aanmaken" class="btn round post popup confirm" title="Deelforum aanmaken">{icon get="add"} </a>
	</div>
{/if}

{include file='forum/head_buttons.tpl'}

<h1>Forum{include file='forum/rss_link.tpl'}</h1>

{foreach from=$categorien item=cat}
	<div class="forumcategorie">
		<h3><a name="{$cat->categorie_id}">{$cat->titel}</a></h3>
		<p class="forumcategorie-omschrijving">{$cat->omschrijving}</p>
		<div class="forumdelen">
			{foreach from=$cat->getForumDelen() item=deel}
				<div class="forumdeel col-md-2">
					<h4><a href="/forum/deel/{$deel->forum_id}">{$deel->titel}</a></h4>
					<p class="forumdeel-omschrijving">{$deel->omschrijving}</p>
				</div>
			{/foreach}
		</div>
	</div>
{/foreach}

<div class="grafiek">
	<h2>Berichten per dag</h2>
	{if LoginModel::mag('P_LOGGED_IN')}
		{include file='forum/stats_grafiek.tpl'}
	{/if}
</div>