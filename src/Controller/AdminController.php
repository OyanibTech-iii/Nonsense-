<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Repository\StockRepository;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Stock;
use App\Form\ProductType;
use App\Form\StockType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ActivityLogger;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(ProductRepository $productRepository, UserRepository $userRepository, StockRepository $stockRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get dashboard statistics
        $totalUsers = $userRepository->count([]);
        $totalProducts = $productRepository->count([]);
        $recentProducts = $productRepository->findBy([], ['id' => 'DESC'], 6);
        
        // Calculate revenue (mock data for now - you can implement real revenue calculation)
        $totalRevenue = 0; // Mock revenue in PHP pesos
        
        // Get active gardens/projects (mock data - you can implement real garden tracking)
        $activeGardens = 0;

        // Build user growth arrays for the last 90 days (daily new users)
        $maxDays = 90;
        $growth = [];
        $dates = [];
        $now = new \DateTimeImmutable();
        for ($i = $maxDays - 1; $i >= 0; $i--) {
            $day = $now->modify(sprintf('-%d days', $i))->setTime(0, 0);
            $start = $day;
            $end = $day->modify('+1 day');

            $qb = $userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.createdAt >= :start')
                ->andWhere('u.createdAt < :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);

            $count = (int) $qb->getQuery()->getSingleScalarResult();
            $growth[] = $count;
            $dates[] = $day->format('M d');
        }

        return $this->render('admin/dashboard.html.twig', [
            'total_users' => $totalUsers,
            'total_products' => $totalProducts,
            'recent_products' => $recentProducts,
            'total_revenue' => $totalRevenue,
            'active_gardens' => $activeGardens,
            'user' => $this->getUser(),
            // provide arrays where most recent items are at the end
            'user_growth' => $growth,
            'user_growth_dates' => $dates,
        ]);
    }

    #[Route('/dashboard/authorize-download', name: 'app_admin_dashboard_authorize', methods: ['POST'])]
    public function authorizeDownload(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // CSRF header check
        $token = $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_dashboard_download', $token)) {
            return new JsonResponse(['authorized' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? '';

        if (empty($password)) {
            return new JsonResponse(['authorized' => false, 'message' => 'Password is required'], 400);
        }

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['authorized' => false, 'message' => 'Not authenticated'], 401);
        }

        if ($passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['authorized' => true]);
        }

        return new JsonResponse(['authorized' => false, 'message' => 'Invalid password'], 403);
    }

    #[Route('/profile', name: 'app_admin_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        return $this->render('admin/profile_content.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        
        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/settings', name: 'app_admin_settings')]
    public function settings(): Response
    {
        return $this->render('admin/settings.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[Route('/products', name: 'app_admin_products')]
    public function products(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();
        
        return $this->render('admin/products.html.twig', [
            'products' => $products,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/stocks', name: 'app_admin_stocks')]
    public function stocks(StockRepository $stockRepository): Response
    {
        $stocks = $stockRepository->findAll();
        
        return $this->render('admin/stocks.html.twig', [
            'stocks' => $stocks,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/users/create', name: 'app_admin_users_create', methods: ['POST'])]
    public function createUser(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_users', $request->headers->get('X-CSRF-TOKEN') ?? '')) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        $data = json_decode($request->getContent(), true);
        
        try {
            $user = new User();
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setEmail($data['email']);
            $user->setPhone($data['phone'] ?? null);
            $user->setIsActive($data['isActive'] ?? true);
            
            // Set role
            $roles = ['ROLE_USER'];
            if (($data['role'] ?? null) === 'ROLE_ADMIN') {
                $roles[] = 'ROLE_ADMIN';
            } elseif (($data['role'] ?? null) === 'ROLE_STAFF') {
                $roles[] = 'ROLE_STAFF';
            }
            $user->setRoles($roles);
            
            // Hash password
            if (!empty($data['password'])) {
                $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }
            
            $entityManager->persist($user);
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'CREATE_USER', sprintf('Created user %s', $user->getEmail()));
            
            return new JsonResponse([
                'success' => true,
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->getId(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhone(),
                    'roles' => $user->getRoles(),
                    'isActive' => $user->isActive(),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error creating user: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/users/{id}/update', name: 'app_admin_users_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateUser(int $id, Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_users', $request->headers->get('X-CSRF-TOKEN') ?? '')) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        $user = $userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        try {
            $data = json_decode($request->getContent(), true);
            
            // Track field changes
            $changes = [];
            
            if ($user->getFirstName() !== ($data['firstName'] ?? null)) {
                $changes['firstName'] = [
                    'from' => $user->getFirstName(),
                    'to' => $data['firstName']
                ];
                $user->setFirstName($data['firstName']);
            }
            
            if ($user->getLastName() !== ($data['lastName'] ?? null)) {
                $changes['lastName'] = [
                    'from' => $user->getLastName(),
                    'to' => $data['lastName']
                ];
                $user->setLastName($data['lastName']);
            }
            
            if ($user->getEmail() !== ($data['email'] ?? null)) {
                $changes['email'] = [
                    'from' => $user->getEmail(),
                    'to' => $data['email']
                ];
                $user->setEmail($data['email']);
            }
            
            if ($user->getPhone() !== ($data['phone'] ?? null)) {
                $changes['phone'] = [
                    'from' => $user->getPhone(),
                    'to' => $data['phone'] ?? null
                ];
                $user->setPhone($data['phone'] ?? null);
            }
            
            if ($user->isActive() !== ($data['isActive'] ?? true)) {
                $changes['isActive'] = [
                    'from' => $user->isActive() ? 'true' : 'false',
                    'to' => ($data['isActive'] ?? true) ? 'true' : 'false'
                ];
                $user->setIsActive($data['isActive'] ?? true);
            }
            
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            // Set role
            $roles = ['ROLE_USER'];
            if (($data['role'] ?? null) === 'ROLE_ADMIN') {
                $roles[] = 'ROLE_ADMIN';
            } elseif (($data['role'] ?? null) === 'ROLE_STAFF') {
                $roles[] = 'ROLE_STAFF';
            }
            
            $oldRoles = implode(',', $user->getRoles());
            $newRoles = implode(',', $roles);
            
            if ($oldRoles !== $newRoles) {
                $changes['roles'] = [
                    'from' => $oldRoles,
                    'to' => $newRoles
                ];
                $user->setRoles($roles);
            }
            
            // Update password if provided
            if (!empty($data['password'])) {
                $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
                $changes['password'] = [
                    'from' => '***',
                    'to' => '***'
                ];
            }
            
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'UPDATE_USER', sprintf('Updated user %s', $user->getEmail()), !empty($changes) ? $changes : null);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->getId(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhone(),
                    'roles' => $user->getRoles(),
                    'isActive' => $user->isActive(),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating user: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/users/{id}/toggle-status', name: 'app_admin_users_toggle_status', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function toggleUserStatus(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, ActivityLogger $activityLogger, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_users', $request->headers->get('X-CSRF-TOKEN') ?? '')) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        $user = $userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        try {
            $data = json_decode($request->getContent(), true);
            
            // Check if deactivating an admin account
            $isDeactivatingAdmin = !$data['isActive'] && in_array('ROLE_ADMIN', $user->getRoles());
            
            if ($isDeactivatingAdmin) {
                // Require password verification for admin deactivation
                if (empty($data['password'])) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Password is required to deactivate an admin account'
                    ], 403);
                }
                
                // Verify the current admin's password
                $currentUser = $this->getUser();
                if (!$passwordHasher->isPasswordValid($currentUser, $data['password'])) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Invalid password. Admin deactivation not allowed.'
                    ], 403);
                }
            }
            
            $changes = [
                'isActive' => [
                    'from' => $user->isActive() ? 'true' : 'false',
                    'to' => $data['isActive'] ? 'true' : 'false'
                ]
            ];
            
            $user->setIsActive($data['isActive']);
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'UPDATE_USER', sprintf('Toggled user %s active=%s', $user->getEmail(), $user->isActive() ? 'yes' : 'no'), $changes);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'User status updated successfully',
                'isActive' => $user->isActive()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating user status: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/users/{id}/delete', name: 'app_admin_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUser(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_users', $request->headers->get('X-CSRF-TOKEN') ?? '')) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        $user = $userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        try {
            $entityManager->remove($user);
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'DELETE_USER', sprintf('Deleted user %s', $user->getEmail()));
            
            return new JsonResponse([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting user: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/users/{id}', name: 'app_admin_users_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserData(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // Admin Product CRUD Routes
    #[Route('/products/new', name: 'app_admin_product_new', methods: ['GET', 'POST'])]
    public function newProduct(Request $request, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('image')->getData();
            if ($imageFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('images_directory');
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // Move the file to the directory where images are stored
                $imageFile->move($uploadsDir, $newFilename);

                // updates the 'image' property to store the image file name
                $product->setImage($newFilename);
            }
            if (!$product->getOwner()) {
                $product->setOwner($this->getUser());
            }

            $entityManager->persist($product);
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'CREATE_PRODUCT', sprintf('Admin created product %s', $product->getName()));

            return $this->redirectToRoute('app_admin_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/product_new.html.twig', [
            'product' => $product,
            'form' => $form,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/products/{id}', name: 'app_admin_product_show', methods: ['GET'])]
    public function showProduct(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/products/{id}/edit', name: 'app_admin_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Request $request, Product $product, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('image')->getData();
            if ($imageFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('images_directory');
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // Move the file to the directory where images are stored
                $imageFile->move($uploadsDir, $newFilename);

                // updates the 'image' property to store the image file name
                $product->setImage($newFilename);
            }
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'UPDATE_PRODUCT', sprintf('Admin updated product %s', $product->getName()));

            return $this->redirectToRoute('app_admin_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/product_edit.html.twig', [
            'product' => $product,
            'form' => $form,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/products/{id}', name: 'app_admin_product_delete', methods: ['POST'])]
    public function deleteProduct(Request $request, Product $product, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'DELETE_PRODUCT', sprintf('Admin deleted product %s', $product->getName()));
        }

        return $this->redirectToRoute('app_admin_products', [], Response::HTTP_SEE_OTHER);
    }

    // Admin Stock CRUD Routes
    #[Route('/stocks/new', name: 'app_admin_stock_new', methods: ['GET', 'POST'])]
    public function newStock(Request $request, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set timestamps
            $stock->setCreatedAt(new \DateTime());
            $stock->setUpdatedAt(new \DateTime());
            
            $entityManager->persist($stock);
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'CREATE_STOCK', sprintf('Admin created stock (Type: %s, Location: %s)', $stock->getStockType(), $stock->getLocation()));

            return $this->redirectToRoute('app_admin_stocks', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/stock_new.html.twig', [
            'stock' => $stock,
            'form' => $form,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/stocks/{id}', name: 'app_admin_stock_show', methods: ['GET'])]
    public function showStock(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/stocks/{id}/edit', name: 'app_admin_stock_edit', methods: ['GET', 'POST'])]
    public function editStock(Request $request, Stock $stock, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update timestamp
            $stock->setUpdatedAt(new \DateTime());
            
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'UPDATE_STOCK', sprintf('Admin updated stock (Type: %s, Location: %s)', $stock->getStockType(), $stock->getLocation()));

            return $this->redirectToRoute('app_admin_stocks', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/stock_edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/stocks/{id}', name: 'app_admin_stock_delete', methods: ['POST'])]
    public function deleteStock(Request $request, Stock $stock, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($stock);
            $entityManager->flush();

            $activityLogger->log($this->getUser(), 'DELETE_STOCK', sprintf('Admin deleted stock (Type: %s, Location: %s)', $stock->getStockType(), $stock->getLocation()));
        }

        return $this->redirectToRoute('app_admin_stocks', [], Response::HTTP_SEE_OTHER);
    }

    // API Endpoints
    #[Route('/api/profile', name: 'app_admin_api_profile', methods: ['GET'])]
    public function apiProfile(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var User $user */
        $user = $this->getUser();
        
        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'profileImage' => $user->getProfileImage(),
            ]
        ]);
    }

    #[Route('/api/profile/update', name: 'app_admin_api_profile_update', methods: ['POST'])]
    public function apiProfileUpdate(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, ActivityLogger $activityLogger): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var User $user */
        $user = $this->getUser();
        
        $changes = [];
        
        try {
            // Update basic information
            if ($request->request->has('firstName')) {
                $newValue = $request->request->getString('firstName');
                if ($user->getFirstName() !== $newValue) {
                    $changes['firstName'] = [
                        'from' => $user->getFirstName(),
                        'to' => $newValue
                    ];
                }
                $user->setFirstName($newValue);
            }
            if ($request->request->has('lastName')) {
                $newValue = $request->request->getString('lastName');
                if ($user->getLastName() !== $newValue) {
                    $changes['lastName'] = [
                        'from' => $user->getLastName(),
                        'to' => $newValue
                    ];
                }
                $user->setLastName($newValue);
            }
            if ($request->request->has('email')) {
                $email = $request->request->getString('email');
                // Check if email is unique (except for current user)
                $existingUser = $userRepository->findOneBy(['email' => $email]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'This email is already in use'
                    ], 400);
                }
                if ($user->getEmail() !== $email) {
                    $changes['email'] = [
                        'from' => $user->getEmail(),
                        'to' => $email
                    ];
                }
                $user->setEmail($email);
            }
            if ($request->request->has('phone')) {
                $newValue = $request->request->getString('phone');
                if ($user->getPhone() !== $newValue) {
                    $changes['phone'] = [
                        'from' => $user->getPhone(),
                        'to' => $newValue
                    ];
                }
                $user->setPhone($newValue);
            }

            // Handle profile image upload
            if ($request->files->has('profileImage')) {
                $uploadedFile = $request->files->get('profileImage');
                if ($uploadedFile) {
                    // Validate file
                    $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $ext = strtolower($uploadedFile->getClientOriginalExtension());
                    
                    if (!in_array($ext, $validExtensions)) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Invalid file type. Only ' . implode(', ', $validExtensions) . ' are allowed'
                        ], 400);
                    }

                    // Generate unique filename
                    $fileName = 'profile_' . $user->getId() . '_' . time() . '.' . $ext;
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Archive old profile image instead of deleting (for audit purposes)
                    if ($user->getProfileImage()) {
                        $oldPath = $this->getParameter('kernel.project_dir') . '/public' . $user->getProfileImage();
                        if (file_exists($oldPath)) {
                            $archiveDir = $uploadDir . '/archive';
                            if (!is_dir($archiveDir)) {
                                mkdir($archiveDir, 0755, true);
                            }
                            // Move to archive with timestamp
                            $archiveFileName = basename($oldPath, '.' . pathinfo($oldPath, PATHINFO_EXTENSION)) . '_' . time() . '.' . pathinfo($oldPath, PATHINFO_EXTENSION);
                            rename($oldPath, $archiveDir . '/' . $archiveFileName);
                        }
                    }

                    // Save new image
                    $uploadedFile->move($uploadDir, $fileName);
                    $newProfilePath = '/uploads/profiles/' . $fileName;
                    $changes['profileImage'] = [
                        'from' => $user->getProfileImage() ?: 'none',
                        'to' => $newProfilePath
                    ];
                    $user->setProfileImage($newProfilePath);
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // Log the activity
            $logTarget = 'Updated own profile';
            if (!empty($changes)) {
                $activityLogger->log($user, 'UPDATE_USER', $logTarget, $changes);
            } else {
                $activityLogger->log($user, 'UPDATE_USER', $logTarget);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/profile/password', name: 'app_admin_api_profile_password', methods: ['POST'])]
    public function apiProfilePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var User $user */
        $user = $this->getUser();
        $currentPassword = $request->request->getString('currentPassword');
        $newPassword = $request->request->getString('newPassword');

        try {
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'New password must be at least 8 characters'
                ], 400);
            }

            // Update password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
