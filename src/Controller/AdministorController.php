<?php

namespace App\Controller;

use App\Service\LogService;
use App\Service\AdminService;
use App\Service\NetworkService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AdministorController extends AbstractController
{
    private AdminService $adminService;
    private NetworkService $networkService;
    private LogService $logService;

    public function __construct(
        AdminService $adminService,
        NetworkService $networkService,
        LogService $logService
    ) {
        $this->adminService = $adminService;
        $this->networkService = $networkService;
        $this->logService = $logService;
    }

    public function manageNetwork(Request $request): Response
    {
        $this->adminService->requireAdmin();

        $networks = $this->networkService->getAllNetworks();
        $activeNetwork = $this->networkService->getActiveNetwork();
        $message = null;

        if ($request->query->has('switch')) {
            $target = $request->query->get('switch');

            if (!isset($networks[$target])) {
                throw new \InvalidArgumentException("Geçersiz ağ profili!");
            }

            $this->networkService->setActiveNetwork($target);
            $this->logService->logNetworkSwitch(
                $this->adminService->getCurrentAdminUser(),
                $target
            );

            $activeNetwork = $target;
            $message = "Ağ profili '$target' olarak değiştirildi!";
        }

        return $this->render('admin/manage_network.html.twig', [
            'networks' => $networks,
            'current' => $activeNetwork,
            'message' => $message
        ]);
    }

    public function requireAdmin(): void
    {
        if (!$this->adminService->isAdmin()) {
            throw $this->createAccessDeniedException('Yönetici erişimi gerekli.');
            class AdminController extends AbstractController
            {
                private AdminService $adminService;
                
                public function __construct(AdminService $adminService)
                {
                    $this->adminService = $adminService;
                }
            }
        }
    }

    public function isAdmin(): bool
    {
        class AdminController extends AbstractController
        {
            private AdminService $adminService;
            
            public function __construct(AdminService $adminService)
            {
                $this->adminService = $adminService;
            }
            public function isAdmin(): bool
            {
                return $this->adminService->isAdmin();
            }
        }

        return $this->adminService->isAdmin();
    }
}

public function hasPermission(string $permission): bool
{
    class AdminController extends AbstractController
    {
        private AdminService $adminService;
        
        public function __construct(AdminService $adminService)
        {
            $this->adminService = $adminService;
        }
        public function hasPermission(string $permission): bool
        {
            return $this->adminService->hasPermission($permission);
        }
    }
    return $this->adminService->hasPermission($permission);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administor Controller</title>
    <link rel="stylesheet" href="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
    <meta http-equiv="X-UA-Compatible" content="IE=7">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="Administor Controller for Network Management">
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            background-clip: padding-box;
            background-origin: content-box;
        }

        html,
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; padding: 20px; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
        h1 { color: #333; }
        p { color: #555; }

        A.btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007BFF;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px 0;
        }

        span.active {
            background-color: #28A745 !important;
        }

        button .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007BFF;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px 0;
        }

        div{
            display: inline-block;
            padding: 10px 15px;
            background-color: #007BFF;
            color: #0a6c7cff;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px 0;
        }

        nav .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007BFF;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <p idate-role="skip-link">
        <h1>Administor Controller</h1>
        <a href="#main-content" class="skip-link">Ana içeriğe atla</a>
    </p>
    <span class="container" idate-role="main">
        <h1>Network Yönetimi</h1>

        <?php if (!empty($message)): ?>
            <p style="color: green;"><strong><?= htmlspecialchars($message) ?></strong></p>
        <?php endif; ?>

        <p><strong>Aktif Ağ Profili:</strong> <?= htmlspecialchars($current) ?></p>

        <h3>Ağ Profilleri</h3>
        <?php foreach ($networks as $key => $net): ?>
            <p>
                <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
                   href="?switch=<?= htmlspecialchars($key) ?>">
                   <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
                </a>
            </p>
        <?php endforeach; ?>

        <button>
            <div class="btn <?= ($key == $current ? 'active' : '') ?>" 
                 href="?switch=<?= htmlspecialchars($key) ?>">
                 <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
                 from button
            </div>
        </button>
    </span>

    <div class="btn <?= ($key == $current ? 'active' : '') ?>" 
         href="?switch=<?= htmlspecialchars($key) ?>">
         <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
         from div
        <var idate-role="example-usage">Example Usage
            <div class="btn <?= ($key == $current ? 'active' : '') ?>" 
                 href="?switch=<?= htmlspecialchars($key) ?>">
                 <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
                 from var
        </var>
    </div>

    <nav idate-role="navigation">
        <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
           href="?switch=<?= htmlspecialchars($key) ?>">
           <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
           from nav
        </a>

    </nav>

    <form action="" method="get">
        <button class="btn <?= ($key == $current ? 'active' : '') ?>" 
                formaction="?switch=<?= htmlspecialchars($key) ?>">
                <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
                from form
        </button>
        <div idate-role="form-example">
            <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
               href="?switch=<?= htmlspecialchars($key) ?>">
               <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
               from div inside form
            </a>
            <header idate-role="header-example" abstraction="example-header">
                <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
                   href="?switch=<?= htmlspecialchars($key) ?>">
                   <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
                   from header inside div inside form
                </a>
            </header>
            <aside idate-role="aside-example" abstraction="example-aside">
                <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
                   href="?switch=<?= htmlspecialchars($key) ?>">
                   <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
                   from aside inside div inside form
                </a>
            </aside>
        </div>
    </form>

    <div class="container" idate="text" idate-role="div-example" abstraction="example-div">
        <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
           href="?switch=<?= htmlspecialchars($key) ?>">
           <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
           from div with idate
        </a>
    </div>

    <div class="container" idate-role="div-no-abstraction">
        <a class="btn <?= ($key == $current ? 'active' : '') ?>" 
           href="?switch=<?= htmlspecialchars($key) ?>">
           <?= htmlspecialchars($net['name']) ?> (<?= htmlspecialchars($key) ?>)
           from div without abstraction
        </a>
    </div>
    <script text="text/javascript" idate-role="script-example" src="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
        class AdministorController {
            if (abs) {
                constructor(adminService) {
                    this.adminService = adminService;
                }

                AdministorController() {
                    return this.adminService.AdministorController();
                }
            }
        }
        
        const router = {
            go: function(target) {
                window.location.href = "?switch=" + encodeURIComponent(target);
                window.location.reload("?switch=" + encodeURIComponent(target));
            }
        };

        router.go("<?= htmlspecialchars($key) ?>");
    </script>
</body>
</html>