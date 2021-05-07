<?php


namespace CsrDelft\controller;

use CsrDelft\Component\Formulier\FormulierFactory;
use CsrDelft\Component\Formulier\FormulierInstance;
use CsrDelft\entity\profiel\Profiel;
use CsrDelft\entity\security\Account;
use CsrDelft\view\datatable\DataTable;
use CsrDelft\view\datatable\GenericDataTableResponse;
use Memcache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

/**
 * Voor eventuele generieke controller methodes.
 *
 * @package CsrDelft\controller
 * @method Account|null getUser()
 */
class AbstractController extends BaseController {
	public static function getSubscribedServices() {
		return parent::getSubscribedServices() + [
				'csr.formulier.factory' => FormulierFactory::class,
				'stek.cache.memcache' => '?'. Memcache::class,
			];
	}

	/**
	 * Haal de DataTable selectie uit POST.
	 *
	 * @return string[]
	 */
	protected function getDataTableSelection(): array
	{
		$selection = $this->get('request_stack')
			->getCurrentRequest()
			->request->filter(DataTable::POST_SELECTION, [], FILTER_SANITIZE_STRING);

		if (is_string($selection) && !empty($selection)) {
			return [$selection];
		}

		return $selection;
	}

	/**
	 * Redirect only to external urls if explicitly allowed
	 * @param string $url
	 * @param int $status
	 * @param bool $allowExternal
	 * @return RedirectResponse
	 */
	protected function csrRedirect($url, $status = 302, $allowExternal = false): RedirectResponse
	{
			if (empty($url) || $url === null) {
				$url = $this->get('request_stack')->getCurrentRequest()->getRequestUri();
			}
			if (!str_starts_with($url, $_ENV['CSR_ROOT']) && !$allowExternal) {
				if (preg_match("/^[?#\/]/", $url) === 1) {
					$url = $_ENV['CSR_ROOT'] . $url;
				} else {
					throw $this->createAccessDeniedException();
				}
			}
			return parent::redirect($url, $status);

	}

	protected function tableData($data): GenericDataTableResponse
	{
		return new GenericDataTableResponse($this->get('serializer'), $data);
	}

	/**
	 * @return string|null
	 */
	protected function getUid(): ?string
	{
		$user = $this->getUser();
		if ($user) {
			return $user->uid;
		}
		return null;
	}

	/**
	 * @return Profiel|null
	 */
	protected function getProfiel(): ?Profiel
	{
		$user = $this->getUser();
		if ($user) {
			return $user->profiel;
		}
		return null;
	}

	protected function createAccessDeniedException(string $message = 'Geen Toegang.', Throwable $previous = null): AccessDeniedException {
		return parent::createAccessDeniedException($message, $previous);
	}

	protected function createNotFoundException(string $message = 'Niet gevonden', Throwable $previous = null): NotFoundHttpException {
		return parent::createNotFoundException($message, $previous);
	}

	/**
	 * Creates and returns a Form instance from the type of the form.
	 * @param string $type
	 * @param null $data
	 * @param array $options
	 * @return FormulierInstance
	 */
	protected function createFormulier(string $type, $data = null, array $options = []): FormulierInstance {
		return $this->container->get('csr.formulier.factory')->create($type, $data, $options);
	}
}
