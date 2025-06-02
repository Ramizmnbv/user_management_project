<?php
// src/Entity/User.php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')] // Ensure this matches your table name, especially if it's a reserved word
#[UniqueEntity(fields: ['email'], message: 'This email is already associated with an account.')] // For form validation
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 180, unique: true)] // Doctrine's unique, DB constraint is king
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?\DateTimeInterface $registrationTime = null;

    #[ORM\Column(length: 50, options: ["default" => "active"])]
    private ?string $status = 'active'; // 'active', 'blocked'

    public function __construct()
    {
        $this->registrationTime = new \DateTime();
        $this->status = 'active';
        $this->roles = ['ROLE_USER'];
    }

    // Getters and Setters for all properties...
    // (id, name, email, roles, password, lastLoginTime, registrationTime, status)

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getUserIdentifier(): string { return (string) $this->email; }
    public function getRoles(): array { $roles = $this->roles; $roles[] = 'ROLE_USER'; return array_unique($roles); }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function getLastLoginTime(): ?\DateTimeInterface { return $this->lastLoginTime; }
    public function setLastLoginTime(?\DateTimeInterface $lastLoginTime): static { $this->lastLoginTime = $lastLoginTime; return $this; }
    public function getRegistrationTime(): ?\DateTimeInterface { return $this->registrationTime; }
    public function setRegistrationTime(\DateTimeInterface $registrationTime): static { $this->registrationTime = $registrationTime; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function eraseCredentials(): void { /* $this->plainPassword = null; */ }
    public function isBlocked(): bool { return $this->status === 'blocked'; }
}
