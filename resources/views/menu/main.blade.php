<nav id="menu" class="cd-nav">
	<ul id="cd-primary-nav" class="cd-primary-nav"
			@if (CsrDelft\model\LidInstellingenModel::get('layout', 'fx') != 'nee') style="opacity:0.8;" @endif>
		@can(P_LOGGED_IN)
			<li>
				<a id="cd-main-trigger" class="mobiel-hidden trigger" href="#menu">
					<img id="cd-user-avatar" class="cd-user-avatar"
							 src="/plaetjes/pasfoto/{{CsrDelft\model\security\LoginModel::getProfiel()->getPasfotoPath(true)}}">
					{{CsrDelft\model\security\LoginModel::getProfiel()->getNaam('civitas')}}
				</a>
				<ul class="cd-secondary-nav">
					@include('menu.main_tree', ['parent' => $root])
				</ul>
			</li>
			<li class="mobiel-hidden"><a class="trigger" href="#search"><i class="fa fa-search"></i></a></li>
		@elsecan
			<li><a href="/">Log in</a></li>
		@endcan
	</ul>
</nav>

