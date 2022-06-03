<?php


namespace CsrDelft\Twig\Extension;


use CsrDelft\entity\groepen\enum\GroepStatus;
use CsrDelft\entity\groepen\GroepLid;
use CsrDelft\entity\profiel\Profiel;
use CsrDelft\entity\security\Account;
use CsrDelft\repository\groepen\BesturenRepository;
use CsrDelft\repository\groepen\CommissiesRepository;
use CsrDelft\service\security\LoginService;
use CsrDelft\service\security\SuService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AccountTwigExtension extends AbstractExtension
{
	/**
	 * @var SuService
	 */
	private $suService;
	/**
	 * @var LoginService
	 */
	private $loginService;
	/**
	 * @var BesturenRepository
	 */
	private $besturenRepository;
	/**
	 * @var CommissiesRepository
	 */
	private $commissiesRepository;

	public function __construct(
		LoginService $loginService,
		BesturenRepository $besturenRepository,
		CommissiesRepository $commissiesRepository,
		SuService $suService
	)
	{
		$this->suService = $suService;
		$this->loginService = $loginService;
		$this->besturenRepository = $besturenRepository;
		$this->commissiesRepository = $commissiesRepository;
	}

	public function getFilters()
	{
		return [
			new TwigFilter('may_su_to', [$this, 'may_su_to']),
		];
	}

	public function getFunctions()
	{
		return [
			new TwigFunction('mag', [$this, 'mag']),
			new TwigFunction('getBestuurslid', [$this, 'getBestuurslid']),
			new TwigFunction('getCommissielid', [$this, 'getCommissielid']),
		];
	}

	/**
	 * Mag de op dit moment ingelogde gebruiker $permissie?
	 *
	 * @param string $permission
	 * @param array|null $allowedAuthenticationMethods
	 * @return bool
	 */
	public function mag($permission, array $allowedAuthenticationMethods = null)
	{
		return $this->loginService->_mag($permission, $allowedAuthenticationMethods);
	}

	public function may_su_to(Account $account)
	{
		return $this->suService->maySuTo($account);
	}

	/**
	 * @param Profiel $profiel
	 * @return GroepLid|null
	 */
	public function getBestuurslid(Profiel $profiel)
	{
		$besturen = $this->besturenRepository->getGroepenVoorLid(
			$profiel,
			[GroepStatus::OT, GroepStatus::HT, GroepStatus::FT]
		);
		if (count($besturen)) {
			return $besturen[0]->getLid($profiel->uid);
		}
		return null;
	}

	/**
	 * @param Profiel $profiel
	 * @return GroepLid[]|\Generator
	 */
	public function getCommissielid(Profiel $profiel)
	{
		$commissies = $this->commissiesRepository->getGroepenVoorLid($profiel);
		foreach ($commissies as $commissie) {
			yield $commissie->getLid($profiel->uid);
		}
	}

}
