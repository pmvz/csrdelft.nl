{*
	mijn_punten.tpl	|	P.W.G. Brussee (brussee@live.nl)
*}
<h2>Corveepunten</h2>
<p>
In de onderstaande tabel is een overzicht te vinden van de punten die u per corveefunctie heeft verdiend met daarachter uw bonus/malus-punten indien van toepassing.
Tussen haakjes staat het aantal keer dat u bent ingedeeld in deze functie.
Het totaal is uw huidige aantal toegekende corveepunten.
De prognose geeft aan hoeveel punten u naar verwachting totaal zal hebben aan het einde van het corveejaar.
</p>
<table class="taken-tabel" style="width: 350px;">
	<thead>
		<tr>
			<th>Functie</th>
			<th>Punten</th>
		</tr>
	</thead>
	<tbody>
{foreach from=$puntenlijst.aantal key=fid item=aantal}
		<tr>
			<td>{$functies[$fid]->getNaam()} ({$aantal})</th>
			<td>{strip}{$puntenlijst.punten[$fid]}
	{if $puntenlijst.bonus[$fid] > 0}
		+
	{/if}
	{if $puntenlijst.bonus[$fid] !== 0}
		{$puntenlijst.bonus[$fid]}
	{/if}
			</td>{/strip}
		</tr>
{/foreach}
		<tr style="font-weight: bold;"><td>Totaal</td><td>{strip}{$puntenlijst.puntenTotaal}
{if $puntenlijst.bonusTotaal > 0}
	+
{/if}
{if $puntenlijst.bonusTotaal !== 0}
	{$puntenlijst.bonusTotaal}
{/if}
		</td></tr>{/strip}
		<tr style="font-weight: bold;"><td>Prognose</td><td>{$puntenlijst.prognose}</td></tr>
		<tr style="font-weight: bold;"><td>Tekort</td><td style="background-color: #{$puntenlijst.prognoseColor};">{$puntenlijst.tekort}</td></tr>
	</tbody>
</table>