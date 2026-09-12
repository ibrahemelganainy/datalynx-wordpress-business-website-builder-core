<?php

namespace BusinessBuilderCore\Core;

use BusinessBuilderCore\Settings\BusinessType;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Settings\Language;
use BusinessBuilderCore\Builder\SectionManager;
use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Builder\CoreSections;
use BusinessBuilderCore\Builder\SectionRenderer;
use BusinessBuilderCore\Builder\PageManager;
use BusinessBuilderCore\Admin\PageAdmin;
use BusinessBuilderCore\REST\PageBuilderAjax;
use BusinessBuilderCore\REST\PaymentWebhook;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Core\Payments\PaymentManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ServiceProvider {

    protected BusinessType $business_type;

    protected SiteSettings $site_settings;

    protected Language $language;

    protected PackManager $pack_manager;

    protected SectionManager $section_manager;

    protected SectionRegistry $section_registry;

    protected CoreSections $core_sections;

    protected SectionRenderer $section_renderer;

    protected PageManager $page_manager;

    protected PageAdmin $page_admin;

    protected PageBuilderAjax $page_builder_ajax;

    protected NotificationManager $notification_manager;

    protected AuditLog $audit_log;

    protected PaymentManager $payment_manager;

    protected PaymentWebhook $payment_webhook;

    protected Container $container;

    /**
     * Register all services.
     */
    public function register(): void {

        /**
         * Create the service container.
         */
        $this->container = new Container();

        /**
         * Core Settings Services.
         */
        $this->business_type = new BusinessType();

        $this->site_settings = new SiteSettings();

        $this->language = new Language();

        /**
         * Builder Services.
         */
        $this->section_manager = new SectionManager();

        $this->section_registry = new SectionRegistry();

        $this->page_manager = new PageManager(
            $this->section_manager
        );

        $this->section_renderer = new SectionRenderer(
            $this->section_registry
        );

        $this->core_sections = new CoreSections(
            $this->section_registry
        );

        /**
         * Admin Services.
         */
        $this->page_admin = new PageAdmin(
            $this->page_manager,
            $this->section_registry
        );

        $this->page_builder_ajax = new PageBuilderAjax(
            $this->page_manager,
            $this->section_registry
        );

        /**
         * Shared infrastructure services.
         */
        $this->notification_manager = new NotificationManager();

        $this->audit_log = new AuditLog();

        $this->payment_manager = new PaymentManager();

        $this->payment_webhook = new PaymentWebhook(
            $this->payment_manager,
            $this->notification_manager,
            $this->audit_log
        );

        /**
         * Register shared services in the container.
         */
        $this->container->set(
            NotificationManager::class,
            $this->notification_manager
        );

        $this->container->set(
            AuditLog::class,
            $this->audit_log
        );

        $this->container->set(
            PaymentManager::class,
            $this->payment_manager
        );

        $this->container->set(
            PaymentWebhook::class,
            $this->payment_webhook
        );
        $this->container->set(
            BusinessType::class,
            $this->business_type
        );

        $this->container->set(
            SiteSettings::class,
            $this->site_settings
        );

        $this->container->set(
            Language::class,
            $this->language
        );

        $this->container->set(
            SectionManager::class,
            $this->section_manager
        );

        $this->container->set(
            SectionRegistry::class,
            $this->section_registry
        );

        $this->container->set(
            PageManager::class,
            $this->page_manager
        );

        $this->container->set(
            SectionRenderer::class,
            $this->section_renderer
        );

        $this->container->set(
            CoreSections::class,
            $this->core_sections
        );

        $this->container->set(
            PageAdmin::class,
            $this->page_admin
        );

        $this->container->set(
            PageBuilderAjax::class,
            $this->page_builder_ajax
        );

        /**
         * Pack Manager.
         */
        $this->pack_manager = new PackManager(
            $this->business_type,
            $this->container
        );

        /**
         * Register Business Packs.
         */
        $this->pack_manager->register(
            'law_firm',
            'BusinessBuilderCore\\Packs\\LawFirm\\LawFirmPack'
        );
    }

    /**
     * Boot services.
     */
    public function boot(): void {

        /**
         * Register Core Builder Sections.
         */
        add_action(
            'init',
            array(
                $this,
                'register_core_sections',
            ),
            5
        );

        /**
         * Boot the current Business Pack.
         */
        $this->pack_manager->boot_current();

        /**
         * Register the payment webhook REST route.
         */
        $this->payment_webhook->register();
    }

    /**
     * Register Core Builder Sections.
     */
    public function register_core_sections(): void {

        $this->core_sections->register();
    }

    /**
     * Get Business Type service.
     */
    public function get_business_type(): BusinessType {

        return $this->business_type;
    }

    /**
     * Get Site Settings service.
     */
    public function get_site_settings(): SiteSettings {

        return $this->site_settings;
    }

    /**
     * Get Language service.
     */
    public function get_language(): Language {

        return $this->language;
    }

    /**
     * Get Pack Manager.
     */
    public function get_pack_manager(): PackManager {

        return $this->pack_manager;
    }

    /**
     * Get Section Manager.
     */
    public function get_section_manager(): SectionManager {

        return $this->section_manager;
    }

    /**
     * Get Section Registry.
     */
    public function get_section_registry(): SectionRegistry {

        return $this->section_registry;
    }

    /**
     * Get Section Renderer.
     */
    public function get_section_renderer(): SectionRenderer {

        return $this->section_renderer;
    }

    /**
     * Get Page Manager.
     */
    public function get_page_manager(): PageManager {

        return $this->page_manager;
    }

    /**
     * Get Page Admin.
     */
    public function get_page_admin(): PageAdmin {

        return $this->page_admin;
    }

    public function get_page_builder_ajax(): PageBuilderAjax {

        return $this->page_builder_ajax;
    }

    /**
     * Get Notification Manager.
     */
    public function get_notification_manager(): NotificationManager {

        return $this->notification_manager;
    }

    /**
     * Get Audit Log.
     */
    public function get_audit_log(): AuditLog {

        return $this->audit_log;
    }

    /**
     * Get Payment Manager.
     */
    public function get_payment_manager(): PaymentManager {

        return $this->payment_manager;
    }

    /**
     * Get Payment Webhook.
     */
    public function get_payment_webhook(): PaymentWebhook {

        return $this->payment_webhook;
    }

    /**
     * Get Container.
     */
    public function get_container(): Container {

        return $this->container;
    }
}
