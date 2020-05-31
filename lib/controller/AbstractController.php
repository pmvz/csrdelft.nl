<?php


namespace CsrDelft\controller;

use CsrDelft\Component\Formulier\FormulierFactory;
use CsrDelft\Component\Formulier\FormulierInstance;
use CsrDelft\entity\profiel\Profiel;
use CsrDelft\entity\security\Account;
use CsrDelft\Component\DataTable\DataTableFactory;
use CsrDelft\view\datatable\DataTable;
use CsrDelft\view\datatable\GenericDataTableResponse;
use Memcache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
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
				'csr.table.factory' => DataTableFactory::class,
				'csr.formulier.factory' => FormulierFactory::class,
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

	protected function tableData($data, $groups = null): GenericDataTableResponse
	{
		return new GenericDataTableResponse($this->get('serializer'), $data, null, null, $groups);
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
	 * Redirect only to external urls if explicitly allowed
	 * @param string $url
	 * @param int $status
	 * @param bool $allowExternal
	 * @return RedirectResponse
	 */
	protected function csrRedirect($url, $status = 302, $allowExternal = false)
	{
		if (empty($url) || $url === null) {
			$url = REQUEST_URI;
		}
		if (!startsWith($url, CSR_ROOT) && !$allowExternal) {
			if (preg_match("/^[?#\/]/", $url) === 1) {
				$url = CSR_ROOT . $url;
			} else {
				throw new CsrToegangException();
			}
		}
		return parent::redirect($url, $status);
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

	protected function createDataTable($entityType, $dataUrl) {
		return $this->container->get(DataTableFactory::class)->create($entityType, $dataUrl)->getTable();
	}

	protected function createDataTableWithType($type) {
		return $this->container->get(DataTableFactory::class)->createWithType($type)->getTable();
	}
}
