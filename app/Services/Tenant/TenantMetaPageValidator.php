<?php



namespace App\Services\Tenant;



use App\Support\TenantScope;

use Illuminate\Support\Facades\Log;

use Illuminate\Validation\ValidationException;



class TenantMetaPageValidator

{

    public function __construct(

        protected TenantMetaPageSearchService $pageSearch

    ) {

    }



    public function resolvePageName(string $pageId, ?string $submittedName): string

    {

        if ($submittedName) {

            return $submittedName;

        }



        $page = $this->pageSearch->resolvePage($pageId);



        if ($page) {

            return $page['name'];

        }



        if ((string) TenantScope::platformPageId() === (string) $pageId) {

            return (string) (TenantScope::platformPageName() ?: 'Facebook Page');

        }



        return 'Facebook Page';

    }



    public function assertPageIsAllowed(string $pageId): void

    {

        try {

            $this->pageSearch->assertPageIsAllowed($pageId);

        } catch (ValidationException $e) {

            throw $e;

        } catch (\Throwable $e) {

            Log::warning('TENANT_PAGE_VALIDATION_FAILED', [

                'page_id' => $pageId,

                'error' => $e->getMessage(),

            ]);



            throw ValidationException::withMessages([

                'meta_page_id' => 'Could not verify this Facebook Page. Search and select it again.',

            ]);

        }

    }



    public static function normalizeWhatsAppNumber(string $value): string

    {

        return preg_replace('/\D+/', '', $value) ?? '';

    }



    public function assertWhatsAppNumber(string $value): string

    {

        $digits = self::normalizeWhatsAppNumber($value);



        if (strlen($digits) < 10 || strlen($digits) > 15) {

            throw ValidationException::withMessages([

                'whatsapp_phone_number' => 'Enter a valid business WhatsApp number with country code (e.g. 14385551234).',

            ]);

        }



        return $digits;

    }

}

