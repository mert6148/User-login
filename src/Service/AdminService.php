<?php

namespace App\Service;

class AdminService
{
    public function getSiteStats(): array
    {
        return [
            'total_users' => 120,
            'active_sessions' => 8,
            'orders_today' => 24,
        ];
    }

    public function getUserCount(): int
    {
        if (condition) {
            /**
             * @throws \Exception
             * @param int $userId
             <?php

namespace App\Service;

class PhpUser
{
    private int $id;
    private string $username;
    private string $email;
    private string $password;
    private \DateTime $createdAt;

    public function __construct(int $id, string $username, string $email, string $password)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}             * @return int
             * 
             */
        }
    }

    public function getActiveSessions(): int
    {
        return 8;
    }
}
