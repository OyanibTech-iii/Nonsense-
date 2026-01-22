<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/me', name: 'app_user_profile_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user?->getId(),
                'firstName' => $user?->getFirstName(),
                'lastName' => $user?->getLastName(),
                'email' => $user?->getEmail(),
                'phone' => $user?->getPhone(),
                'profileImage' => $user?->getProfileImage(),
                'roles' => $user?->getRoles(),
            ],
        ]);
    }

    #[Route('/update', name: 'app_user_profile_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, ActivityLogger $activityLogger): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

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
                $existingUser = $userRepository->findOneBy(['email' => $email]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'This email is already in use',
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
                    $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $ext = strtolower($uploadedFile->getClientOriginalExtension());

                    if (!in_array($ext, $validExtensions, true)) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Invalid file type. Only ' . implode(', ', $validExtensions) . ' are allowed',
                        ], 400);
                    }

                    $fileName = 'profile_' . $user->getId() . '_' . time() . '.' . $ext;
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';

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
                'message' => 'Profile updated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/password', name: 'app_user_profile_password', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $currentPassword = $request->request->getString('currentPassword');
        $newPassword = $request->request->getString('newPassword');

        try {
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }

            if (strlen($newPassword) < 8) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'New password must be at least 8 characters',
                ], 400);
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // Log the activity
            $changes = [
                'password' => [
                    'from' => '***',
                    'to' => '***'
                ]
            ];
            $activityLogger->log($user, 'UPDATE_USER', sprintf('Changed password: %s', $user->getEmail()), $changes);

            return new JsonResponse([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
}


