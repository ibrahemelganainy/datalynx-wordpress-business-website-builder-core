<?php

namespace BusinessBuilderCore\Packs\LawFirm;

use BusinessBuilderCore\Packs\LawFirm\PostTypes\Lawyer;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\LegalService;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\LegalServiceFields;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\Testimonial;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\TestimonialFields;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\Faq;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\FaqFields;
use BusinessBuilderCore\Packs\LawFirm\PostTypes\Consultation;
use BusinessBuilderCore\Packs\LawFirm\Taxonomies\PracticeArea;
use BusinessBuilderCore\Packs\LawFirm\Taxonomies\PracticeAreaFields;
use BusinessBuilderCore\Packs\LawFirm\Taxonomies\FaqCategory;
use BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile;
use BusinessBuilderCore\Packs\LawFirm\Frontend\ConsultationForm;
use BusinessBuilderCore\Packs\LawFirm\Admin\ConsultationAdmin;
use BusinessBuilderCore\Packs\LawFirm\Admin\PaymentSettingsAdmin;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardAdmin;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentAdmin;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Availability;
use BusinessBuilderCore\Packs\LawFirm\Appointments\BookingForm;
use BusinessBuilderCore\Core\Notifications\NotificationManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Packs\LawFirm\Starter\StarterSite;
use BusinessBuilderCore\Packs\LawFirm\Starter\StarterAdmin;
use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Builder\PageManager;
use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmSections;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LawFirmPack {

    protected Lawyer $lawyer;

    protected LawyerFields $lawyer_fields;

    protected PracticeArea $practice_area;

    protected PracticeAreaFields $practice_area_fields;

    protected FaqCategory $faq_category;

    protected LawyerProfile $lawyer_profile;

    protected ConsultationForm $consultation_form;

    protected Consultation $consultation;

    protected ConsultationAdmin $consultation_admin;

    protected PaymentSettingsAdmin $payment_settings_admin;

    protected DashboardAdmin $dashboard_admin;

    protected Appointment $appointment;

    protected AppointmentAdmin $appointment_admin;

    protected Availability $availability;

    protected BookingForm $booking_form;

    protected LegalService $legal_service;

    protected LegalServiceFields $legal_service_fields;

    protected Testimonial $testimonial;

    protected TestimonialFields $testimonial_fields;

    protected Faq $faq;

    protected FaqFields $faq_fields;

    protected LawFirmSections $sections;

    protected StarterSite $starter_site;

    protected StarterAdmin $starter_admin;

    /**
     * Constructor.
     */
    public function __construct(
        SectionRegistry $section_registry,
        PageManager $page_manager,
        NotificationManager $notification_manager,
        AuditLog $audit_log,
        PaymentManager $payment_manager,
        SiteSettings $site_settings
    ) {

        $this->lawyer = new Lawyer();

        $this->lawyer_fields = new LawyerFields();

        $this->legal_service = new LegalService();

        $this->legal_service_fields = new LegalServiceFields();

        $this->testimonial = new Testimonial();

        $this->testimonial_fields = new TestimonialFields();

        $this->faq = new Faq();

        $this->faq_fields = new FaqFields();

        $this->practice_area = new PracticeArea();

        $this->practice_area_fields = new PracticeAreaFields();

        $this->faq_category = new FaqCategory();

        $this->lawyer_profile = new LawyerProfile();

        $this->starter_site = new StarterSite( $page_manager );

        $this->starter_admin = new StarterAdmin( $this->starter_site );

        $this->consultation_form = new ConsultationForm();

        $this->consultation = new Consultation();

        $this->consultation_admin = new ConsultationAdmin(
            $notification_manager,
            $audit_log
        );

        $this->payment_settings_admin = new PaymentSettingsAdmin(
            $payment_manager,
            $audit_log,
            $site_settings
        );

        $this->dashboard_admin = new DashboardAdmin(
            $notification_manager
        );

        $this->appointment = new Appointment();

        $this->availability = new Availability();

        $this->appointment_admin = new AppointmentAdmin(
            $notification_manager,
            $audit_log
        );

        $this->booking_form = new BookingForm(
            $notification_manager,
            $audit_log,
            $this->availability
        );

        $this->sections = new LawFirmSections(
            $section_registry
        );
    }

    /**
     * Register Law Firm functionality.
     */
    public function register(): void {

        $this->lawyer->register();

        $this->lawyer_fields->register();

        $this->legal_service->register();

        $this->legal_service_fields->register();

        $this->testimonial->register();

        $this->testimonial_fields->register();

        $this->faq->register();

        $this->faq_fields->register();

        $this->practice_area->register();

        $this->practice_area_fields->register();

        $this->faq_category->register();

        $this->lawyer_profile->register();

        $this->consultation_form->register();

        $this->consultation->register();

        $this->consultation_admin->register();

        $this->payment_settings_admin->register();

        $this->dashboard_admin->register();

        $this->appointment->register();

        $this->appointment_admin->register();

        $this->booking_form->register();

        $this->starter_admin->register();

        $this->sections->register();

    }

    /**
     * Boot Law Firm functionality.
     */
    public function boot(): void {

        /*
         * Self-healing rewrite flush (spec 11).
         *
         * The bb_lawyer / bb_legal_service CPTs and the practice-area
         * taxonomy expose pretty permalinks. If the stored rewrite rules
         * were generated before these were registered (common when the
         * plugin was activated before the CPTs existed), singles fall
         * back to the ugly "?p=ID" form and /lawyers/{slug}/ 404s.
         *
         * We flush ONCE - only when the stored rule set is missing the
         * pack's slugs - tracked by a version option so it never runs on
         * every request. This is idempotent and safe to run per site on
         * Multisite (the option is site-specific).
         */
        add_action(
            'init',
            array( $this, 'maybe_flush_rewrite_rules' ),
            20
        );
    }

    /**
     * Flush rewrite rules once if the pack's permalinks are missing.
     */
    public function maybe_flush_rewrite_rules(): void {

        $version = '1';

        if ( get_option( 'bb_lawfirm_rewrite_version' ) === $version ) {
            return;
        }

        $rules = get_option( 'rewrite_rules' );

        $needs_flush = ! is_array( $rules )
            || ! preg_grep( '/bb_lawyer/', array_keys( $rules ) ) ;

        if ( ! $needs_flush ) {

            /*
             * Rules already look correct - record the version so we
             * never re-check on a future request.
             */
            update_option( 'bb_lawfirm_rewrite_version', $version );

            return;
        }

        /*
         * Registering the CPTs/taxonomies must already have happened on
         * this request (their own init hooks run earlier), so a flush
         * here regenerates the correct rules. Only users who can manage
         * options trigger the (slightly costly) flush; everyone else is
         * served as-is until an admin request performs it.
         */
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        flush_rewrite_rules( false );

        update_option( 'bb_lawfirm_rewrite_version', $version );
    }
}
