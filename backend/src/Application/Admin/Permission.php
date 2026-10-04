<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** Permission names (table `permissions`, seeded by migration 007). */
final class Permission
{
    public const ENQUIRIES_READ = 'enquiries.read';
    public const ENQUIRIES_UPDATE = 'enquiries.update';
    public const USERS_MANAGE = 'users.manage';
    public const AUDIT_READ = 'audit.read';
    /** Save drafts of products and services (D-011). */
    public const CONTENT_EDIT = 'content.edit';
    /** Put drafts live, show or hide products and services (D-011). */
    public const CONTENT_PUBLISH = 'content.publish';
    /** See the visitor analytics (D-014). */
    public const ANALYTICS_READ = 'analytics.read';
    /** See applications and download CVs (D-019). */
    public const RECRUITMENT_READ = 'recruitment.read';
    /** Move applications through the stages, score, add notes, email candidates (D-019). */
    public const RECRUITMENT_MANAGE = 'recruitment.manage';
    /** Edit, publish and close the roles on careers.paxofi.com (D-018). */
    public const CAREERS_EDIT = 'careers.edit';
}
