<?php

namespace CsrDelft\entity\civimelder;

use CsrDelft\repository\civimelder\DeelnemerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeelnemerRepository::class)
 * @ORM\Table(name="civimelder_deelnemer")
 */
class Deelnemer
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Activiteit::class, inversedBy="deelnemers")
     * @ORM\JoinColumn(nullable=false)
     */
    private $activiteit;

    /**
     * @ORM\Column(type="integer")
     */
    private $aantal;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActiviteit(): ?Activiteit
    {
        return $this->activiteit;
    }

    public function setActiviteit(?Activiteit $activiteit): self
    {
        $this->activiteit = $activiteit;

        return $this;
    }

    public function getAantal(): ?int
    {
        return $this->aantal;
    }

    public function setAantal(int $aantal): self
    {
        $this->aantal = $aantal;

        return $this;
    }
}
