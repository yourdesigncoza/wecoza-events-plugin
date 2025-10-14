<?php
declare(strict_types=1);

namespace WeCozaEvents\Support;

use PDO;
use WeCozaEvents\Controllers\JsonResponder;
use WeCozaEvents\Database\Connection;
use WeCozaEvents\Models\ClassTaskRepository;
use WeCozaEvents\Services\ClassTaskService;
use WeCozaEvents\Services\TaskManager;
use WeCozaEvents\Services\TaskTemplateRegistry;
use WeCozaEvents\Views\Presenters\ClassTaskPresenter;
use WeCozaEvents\Views\TemplateRenderer;

final class Container
{
    private static ?PDO $pdo = null;
    private static ?string $schema = null;
    private static ?TaskTemplateRegistry $taskTemplateRegistry = null;
    private static ?ClassTaskRepository $classTaskRepository = null;
    private static ?TaskManager $taskManager = null;
    private static ?ClassTaskService $classTaskService = null;
    private static ?ClassTaskPresenter $classTaskPresenter = null;
    private static ?TemplateRenderer $templateRenderer = null;
    private static ?WordPressRequest $wordpressRequest = null;
    private static ?JsonResponder $jsonResponder = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = Connection::getPdo();
        }

        return self::$pdo;
    }

    public static function schema(): string
    {
        if (self::$schema === null) {
            self::$schema = Connection::getSchema();
        }

        return self::$schema;
    }

    public static function taskTemplateRegistry(): TaskTemplateRegistry
    {
        if (self::$taskTemplateRegistry === null) {
            self::$taskTemplateRegistry = new TaskTemplateRegistry();
        }

        return self::$taskTemplateRegistry;
    }

    public static function classTaskRepository(): ClassTaskRepository
    {
        if (self::$classTaskRepository === null) {
            self::$classTaskRepository = new ClassTaskRepository(self::pdo(), self::schema());
        }

        return self::$classTaskRepository;
    }

    public static function taskManager(): TaskManager
    {
        if (self::$taskManager === null) {
            self::$taskManager = new TaskManager(self::pdo(), self::schema(), self::taskTemplateRegistry());
        }

        return self::$taskManager;
    }

    public static function classTaskService(): ClassTaskService
    {
        if (self::$classTaskService === null) {
            self::$classTaskService = new ClassTaskService(
                self::classTaskRepository(),
                self::taskManager(),
                self::taskTemplateRegistry()
            );
        }

        return self::$classTaskService;
    }

    public static function classTaskPresenter(): ClassTaskPresenter
    {
        if (self::$classTaskPresenter === null) {
            self::$classTaskPresenter = new ClassTaskPresenter();
        }

        return self::$classTaskPresenter;
    }

    public static function templateRenderer(): TemplateRenderer
    {
        if (self::$templateRenderer === null) {
            self::$templateRenderer = new TemplateRenderer();
        }

        return self::$templateRenderer;
    }

    public static function wordpressRequest(): WordPressRequest
    {
        if (self::$wordpressRequest === null) {
            self::$wordpressRequest = new WordPressRequest();
        }

        return self::$wordpressRequest;
    }

    public static function jsonResponder(): JsonResponder
    {
        if (self::$jsonResponder === null) {
            self::$jsonResponder = new JsonResponder();
        }

        return self::$jsonResponder;
    }
}
