<?php

namespace Src\Organization\Domain;

/** The legal form of an entity of the group (`docs/design/erp-organization.md` section 7). */
enum LegalForm: string
{
    case Pt = 'pt';
    case PtTbk = 'pt_tbk';
    case Cv = 'cv';
    case Firma = 'firma';
    case Koperasi = 'koperasi';
    case Yayasan = 'yayasan';
    case Perum = 'perum';
    case Persero = 'persero';
    case Other = 'other';
}
