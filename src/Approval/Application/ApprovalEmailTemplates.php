<?php

namespace Src\Approval\Application;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The email templates of the Approval context, registered in the shared catalog by its service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class ApprovalEmailTemplates
{
    public const string REQUESTED = 'approval.requested';

    public const string REMINDER = 'approval.reminder';

    public const string GRANTED = 'approval.granted';

    public const string REJECTED = 'approval.rejected';

    public const string RETURNED = 'approval.returned';

    public const string APPROVER_VACATED = 'approval.approver_vacated';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        $variables = [
            new EmailVariable('name', 'The recipient\'s name', 'Budi'),
            new EmailVariable('title', 'What is waiting for approval', 'Backend Developer (2 orang)'),
            new EmailVariable('requester', 'The name of the person who asked', 'Rina'),
        ];
        $withReason = [...$variables, new EmailVariable('reason', 'The reason the approver gave', 'Tambahkan perkiraan anggaran.')];

        return [
            new EmailTemplateDefinition(
                self::REQUESTED, 'Sent to an approver when an approval reaches their step.', $variables,
                TranslatedText::fromArray(['id' => 'Menunggu persetujuan Anda: {{title}}', 'en' => 'Waiting for your approval: {{title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n{{requester}} mengajukan **{{title}}** dan menunggu persetujuan Anda. Buka daftar persetujuan Anda untuk menyetujui, menolak, atau mengembalikannya untuk direvisi.",
                    'en' => "Hello {{name}},\n\n{{requester}} submitted **{{title}}** and it is waiting for your approval. Open your approvals to approve it, reject it, or return it for revision.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::REMINDER, 'Sent to an approver again while an approval still waits for their decision.', $variables,
                TranslatedText::fromArray(['id' => 'Pengingat: {{title}} masih menunggu persetujuan Anda', 'en' => 'Reminder: {{title}} is still waiting for your approval']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\n**{{title}}** dari {{requester}} masih menunggu keputusan Anda. Buka daftar persetujuan Anda untuk memutuskannya.",
                    'en' => "Hello {{name}},\n\n**{{title}}** from {{requester}} is still waiting for your decision. Open your approvals to decide it.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::GRANTED, 'Sent to the person who asked when every step of the approval was approved.', $variables,
                TranslatedText::fromArray(['id' => 'Disetujui: {{title}}', 'en' => 'Approved: {{title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPengajuan Anda **{{title}}** telah disetujui.",
                    'en' => "Hello {{name}},\n\nYour request **{{title}}** was approved.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::REJECTED, 'Sent to the person who asked when an approver rejected the request.', $withReason,
                TranslatedText::fromArray(['id' => 'Ditolak: {{title}}', 'en' => 'Rejected: {{title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPengajuan Anda **{{title}}** ditolak.\n\nAlasan: {{reason}}",
                    'en' => "Hello {{name}},\n\nYour request **{{title}}** was rejected.\n\nReason: {{reason}}",
                ]),
            ),
            new EmailTemplateDefinition(
                self::RETURNED, 'Sent to the person who asked when an approver returned the request for revision.', $withReason,
                TranslatedText::fromArray(['id' => 'Dikembalikan untuk revisi: {{title}}', 'en' => 'Returned for revision: {{title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nPengajuan Anda **{{title}}** dikembalikan untuk direvisi.\n\nCatatan: {{reason}}\n\nAnda dapat memperbaikinya dan mengajukannya lagi.",
                    'en' => "Hello {{name}},\n\nYour request **{{title}}** was returned for revision.\n\nNote: {{reason}}\n\nYou can fix it and submit it again.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::APPROVER_VACATED, 'Sent to the person who asked when an approver left while their step waited.', $variables,
                TranslatedText::fromArray(['id' => 'Pemberi persetujuan berhenti: {{title}}', 'en' => 'An approver left: {{title}}']),
                TranslatedText::fromArray([
                    'id' => "Halo {{name}},\n\nSalah satu pemberi persetujuan untuk **{{title}}** tidak lagi bergabung. Admin akan menunjuk penggantinya; pengajuan Anda tetap menunggu.",
                    'en' => "Hello {{name}},\n\nOne of the approvers of **{{title}}** is no longer with the company. An admin names a replacement; your request keeps waiting.",
                ]),
            ),
        ];
    }
}
