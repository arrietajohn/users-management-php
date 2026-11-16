<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../Application/Ports/Out/ActivityLogPort.php';

final class ActivityLogRepositoryMySQL implements ActivityLogPort
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(?string $actorUserId, string $action, string $subjectType, ?string $subjectId, array $details = array()): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO activity_logs (actor_user_id, action, subject_type, subject_id, details) ' .
            'VALUES (:actor, :action, :type, :subject, :details)'
        );
        $statement->execute(array(
            ':actor' => $actorUserId,
            ':action' => mb_substr($action, 0, 80),
            ':type' => mb_substr($subjectType, 0, 40),
            ':subject' => $subjectId,
            ':details' => $details === array() ? null : json_encode($details, JSON_THROW_ON_ERROR),
        ));
    }

    public function recent(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.action, a.subject_type, a.subject_id, u.name AS actor_name, a.created_at ' .
            'FROM activity_logs a LEFT JOIN users u ON u.id = a.actor_user_id ' .
            'ORDER BY a.created_at DESC, a.id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', min(20, max(1, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn (array $row): array => array(
            'action' => (string) $row['action'],
            'subject_type' => (string) $row['subject_type'],
            'subject_id' => $row['subject_id'] === null ? null : (string) $row['subject_id'],
            'actor_name' => $row['actor_name'] === null ? null : (string) $row['actor_name'],
            'created_at' => (string) $row['created_at'],
        ), $statement->fetchAll());
    }
}
