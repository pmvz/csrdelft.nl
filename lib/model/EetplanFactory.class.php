<?php

/**
 * EetplanFactory.class.php
 *
 * @author G.J.W. Oolbekkink <g.j.w.oolbekkink@gmail.com>
 *
 * Verzorgt het aanmaken van een nieuw eetplan, gebasseerd op een bak met data
 */
class EetplanFactory {
    /**
     * Avond van deze nieuwe sessie
     *
     * @var string
     */
    private $avond;
    /**
     * Lijst van novieten die elkaar gezien hebben
     *
     * $bekenden[$noviet1][$noviet2] = true;
     * $bekenden[$noviet2][$noviet1] = true;
     *
     * Beide manieren moeten gezet worden.
     *
     * @var array
     */
    private $bekenden;
    /**
     * Lijst van novieten die huizen bezocht hebben.
     *
     * $bezocht[$huis][] = $sjaars;
     *
     * @var array
     */
    private $bezocht;
    /**
     * Lijst van novieten die huizen op een bepaalde avond bezocht hebben.
     *
     * $bezocht_ah[$avond][$huis][] = $sjaars;
     *
     * @var array
     */
    private $bezocht_ah;
    /**
     * Lijst van novieten die huizen bezocht hebben (gebasseerd op noviet).
     *
     * $bezocht_sh[$sjaars][$huis] = true;
     *
     * @var array
     */
    private $bezocht_sh;

    /**
     * Sjaars - Avond - Huis
     *
     * $sah[$noviet][$avond][] = $huis;
     *
     * @var array
     */
    private $sah;

    /**
     * Avond - Huis - Sjaars
     *
     * $ahs[$avond][$huis][] = $sjaars;
     *
     * @var array
     */
    private $ahs;

    /**
     * @var Profiel[]
     */
    private $novieten;

    /**
     * @var Woonoord[]
     */
    private $huizen;

    public function __construct() {

    }

    /**
     * @param EetplanBekenden[] $bekenden
     */
    public function setBekenden(array $bekenden) {
        $this->bekenden = array();
        foreach ($bekenden as $eetplanBekenden) {
            $noviet1 = $eetplanBekenden->uid1;
            $noviet2 = $eetplanBekenden->uid2;
            $this->bekenden[$noviet1][$noviet2] = true;
            $this->bekenden[$noviet2][$noviet1] = true;
        }
    }

    /**
     * @param Eetplan[] $bezochten
     */
    public function setBezocht(array $bezochten) {
        $this->bezocht = array();
        $this->bezocht_ah = array();
        $this->bezocht_sh = array();
        foreach ($bezochten as $sessie) {
            $huis = $sessie->woonoord_id;
            $noviet = $sessie->uid;
            $avond = $sessie->avond;
            $this->bezocht[$huis][] = $noviet;
            $this->bezocht_sh[$noviet][$huis] = true;
            $this->bezocht_ah[$avond][$huis][] = $noviet;
            $this->sah[$noviet][$avond] = $huis;
            $this->ahs[$avond][$huis] = $noviet;
        }
    }

    /**
     * @param Profiel[] $novieten
     */
    public function setNovieten(array $novieten) {
        $this->novieten = $novieten;
    }

    /**
     * @param Woonoord[] $huizen
     */
    public function setHuizen(array $huizen) {
        $this->huizen = $huizen;
    }

    /**
     * Genereer een eetplansessie voor deze avond
     *
     * @param string $avond
     * @param bool $random
     * @return array
     */
    public function genereer($avond, $random = true) {
        assert(isset($this->novieten));
        assert(isset($this->huizen));

        $eetplan = array();

        $s = count($this->novieten);
        $h = count($this->huizen);

        if ($random == 0) {
            $ih = 1;
        } else {
            $ih = rand(1, $h);
        }

        foreach ($this->novieten as $noviet) {
            $uid = $noviet->uid;
            # wat foutmeldingen voorkomen
            if (!isset($this->ahs[$avond][$ih]))
                $this->ahs[$avond][$ih] = array();
            if (!isset($this->bekenden[$uid]))
                $this->bekenden[$uid] = array();
            # we hebben nu een avond en een sjaars, nu nog een huis voor m vinden...
            # zolang
            # - deze sjaars dit huis al bezocht heeft, of
            # - in het huidige huis (ih) een sjaars zit die deze sjaars (is) al ontmoet heeft
            # - het huis nog niet aan zn max sjaars is voor deze avond
            # nemen we het volgende huis
            $startih = $ih;
            # nieuw: begin met het max aantal sjaars per huis net iets te laag in te stellen, zodat
            # de huizen eerst goed vol komen, en daarna pas extra sjaars bij huizen
            $m = (int) floor($s / $h);
            $nofm = 0; # aantal huizen dat aan de max zit.
            while (isset($this->bezocht_sh[$uid][$ih])
                or count(array_intersect($this->ahs[$avond][$ih], $this->bekenden[$uid])) > 0
                or count($this->bezocht_ah[$avond][$ih]) >= $m) {
                $ih = $ih % $h + 1;
                if ($ih == $startih) {
                    $m++; #die ('whraagh!!!');
                }
                if (!isset($this->ahs[$avond][$ih])) {
                    $this->ahs[$avond][$ih] = array();
                }

                # nieuw: als alle huizen zijn langsgelopen en ze allemaal max sjaars hebben
                # dan de max ophogen
                if (count($this->bezocht_ah[$avond][$ih]) == $m)
                    $nofm++;
                if ($nofm == $h)
                    $m++;
            }

            # deze sjaars heeft op deze avond een huis gevonden
            $this->sah[$uid][$avond] = $ih;
            # en gaat alle sjaars die op deze avond in dit huis zitten dat melden
            foreach ($this->ahs[$avond][$ih] as $sjaars) {
                $this->bekenden[$uid][] = $sjaars; # alle sjaars in mijn seen
                $this->bekenden[$sjaars][] = $uid; # ik in alle sjaars' seen
            }
            $this->ahs[$avond][$ih][] = $uid;
            # de sjaars heeft het huis bezocht
            $this->bezocht[$ih][] = $uid;
            $this->bezocht_sh[$uid][$ih] = true;
            $this->bezocht_ah[$avond][$ih][] = $uid;

            # Maak een entity voor deze sessie
            $nieuweetplan = new Eetplan();
            $nieuweetplan->avond = $avond;
            $nieuweetplan->uid = $uid;
            $nieuweetplan->woonoord_id = $this->huizen[$ih];

            $eetplan[] = $nieuweetplan;

            # huis ophogen
            if ($random == 0)
                $ih = $ih % $h + 1;
            else
                $ih = rand(1, $h);
        }

        return $eetplan;
    }
}
