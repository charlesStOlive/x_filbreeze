<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @mixin IdeHelperCompany
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property \App\Enums\CompanyType|null $type
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property int|null $sector_id
 * @property int|null $is_ex
 * @property int|null $nb_collab
 * @property string|null $address
 * @property string|null $cp
 * @property string|null $city
 * @property \App\Enums\Country|null $country
 * @property string|null $tel
 * @property string|null $site_url
 * @property string|null $email
 * @property string|null $siret
 * @property string|null $longitude
 * @property string|null $latitude
 * @property float|null $distance
 * @property string|null $memo
 * @property array<array-key, mixed>|null $others
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contact> $contacts
 * @property-read int|null $contacts_count
 * @property-read \App\Models\ImageCloudinary|null $logo_cloudinary
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \App\Models\Sector|null $sector
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereDistance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereIsEx($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereNbCollab($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereOthers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePrimaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSecondaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSectorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSiret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereTel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt($value)
 */
	class Company extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $full_name
 * @property string|null $civ
 * @property string $email
 * @property int|null $is_ex
 * @property int|null $company_id
 * @property string|null $tel
 * @property string|null $linkedin_ext_id
 * @property string|null $memo
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereIsEx($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereLinkedinExtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereTel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereUpdatedAt($value)
 */
	class Contact extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gamme whereUpdatedAt($value)
 */
	class Gamme extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string|null $collection
 * @property string $file_name
 * @property string $url
 * @property string $public_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereCollection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary wherePublicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageCloudinary whereUrl($value)
 */
	class ImageCloudinary extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin IdeHelperInvoice
 * @property int $id
 * @property string|null $code
 * @property string|null $title
 * @property \App\Models\States\Invoice\InvoiceState|null $state
 * @property string|null $modalite
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $items
 * @property float|null $total_ht_br
 * @property float|null $total_ht
 * @property int|null $has_tva
 * @property string|null $tx_tva
 * @property float|null $tva
 * @property float|null $total_ttc
 * @property \Illuminate\Support\Carbon|null $submited_at
 * @property string|null $submited_at_my
 * @property string|null $submited_at_qy
 * @property \Illuminate\Support\Carbon|null $payed_at
 * @property string|null $payed_at_my
 * @property string|null $payed_at_qy
 * @property int|null $number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $created_at_my
 * @property string|null $created_at_qy
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Contact|null $contact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read int|null $quotes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice orWhereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice orWhereState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAtMy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAtQy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereHasTva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereModalite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePayedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePayedAtMy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePayedAtQy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubmitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubmitedAtMy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubmitedAtQy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalHt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalHtBr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalTtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTxTva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 */
	class Invoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $msg_user_draft_id
 * @property string $status
 * @property string|null $services_options
 * @property string|null $services_results
 * @property string|null $data_mail
 * @property string|null $from
 * @property string|null $subject
 * @property string|null $tos
 * @property string|null $email_id
 * @property string|null $email_original_id
 * @property string|null $errors
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property $services_options.d-cor.mode
 * @property $services_options.d-cor.code
 * @property $services_options.d-trad.mode
 * @property $services_options.d-trad.code
 * @property $services_results.d-cor.success
 * @property $services_results.d-cor.reason
 * @property $services_results.d-cor.code
 * @property $services_results.d-cor.code_options
 * @property $services_results.d-cor.errors
 * @property $services_results.d-trad.success
 * @property $services_results.d-trad.reason
 * @property $services_results.d-trad.code
 * @property $services_results.d-trad.code_options
 * @property $services_results.d-trad.errors
 * @property-read \App\Models\MsgUserDraft|null $msg_email_user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereDataMail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereEmailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereEmailOriginalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereErrors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereMsgUserDraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereServicesOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereServicesResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereTos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailDraft whereUpdatedAt($value)
 */
	class MsgEmailDraft extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $msg_user_in_id
 * @property string $status
 * @property string|null $services_options
 * @property string|null $services_results
 * @property string|null $data_mail
 * @property string|null $from
 * @property string|null $subject
 * @property string|null $tos
 * @property string|null $email_id
 * @property string|null $email_original_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $errors
 * @property $services_options.e-in-a.mode
 * @property $services_options.e-in-a.field
 * @property $services_results.e-in-a.success
 * @property $services_results.e-in-a.reason
 * @property $services_results.e-in-a.newfolder
 * @property-read \App\Models\MsgUserIn|null $msg_email_user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereDataMail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereEmailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereEmailOriginalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereErrors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereMsgUserInId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereServicesOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereServicesResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereTos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgEmailIn whereUpdatedAt($value)
 */
	class MsgEmailIn extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string $expires
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereExpires($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgToken whereUserId($value)
 */
	class MsgToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $ms_id
 * @property string $email
 * @property string|null $subscription_id
 * @property string|null $services_options
 * @property string|null $abn_secret
 * @property \Illuminate\Support\Carbon|null $expire_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $user_id
 * @property $services_options.d-cor.mode
 * @property $services_options.d-cor.code
 * @property $services_options.d-trad.mode
 * @property $services_options.d-trad.code
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MsgEmailDraft> $msg_email_drafts
 * @property-read int|null $msg_email_drafts_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereAbnSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereExpireAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereMsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereServicesOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserDraft whereUserId($value)
 */
	class MsgUserDraft extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $ms_id
 * @property string $email
 * @property string|null $subscription_id
 * @property string|null $services_options
 * @property string|null $abn_secret
 * @property \Illuminate\Support\Carbon|null $expire_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property $services_options.e-in-a.mode
 * @property $services_options.e-in-a.field
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MsgEmailIn> $msg_email_ins
 * @property-read int|null $msg_email_ins_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereAbnSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereExpireAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereMsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereServicesOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MsgUserIn whereUpdatedAt($value)
 */
	class MsgUserIn extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin IdeHelperProduct
 * @property int $id
 * @property string $code
 * @property string $title
 * @property \App\Enums\ProductType $type
 * @property int|null $gamme_id
 * @property string $unit_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read \App\Models\Gamme|null $gamme
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereGammeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin IdeHelperQuote
 * @property int $id
 * @property string|null $code
 * @property string|null $title
 * @property \App\Models\States\Quote\QuoteState|null $state
 * @property int|null $version
 * @property int $is_retained
 * @property string|null $parent_id
 * @property string|null $end_at
 * @property int|null $projet_id
 * @property int|null $app_id
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $items
 * @property float|null $total_ht
 * @property float|null $total_ht_br
 * @property float|null $total_ttc
 * @property int|null $number
 * @property string|null $validated_at
 * @property string|null $validated_at_my
 * @property string|null $validated_at_qy
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property float|null $total_options
 * @property string|null $total_jours
 * @property float|null $total_avant_options
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Contact|null $contact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote orWhereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote orWhereState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereIsRetained($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereProjetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalAvantOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalHt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalHtBr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalJours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereTotalTtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereValidatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereValidatedAtMy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereValidatedAtQy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quote withRemainingAmount()
 */
	class Quote extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin IdeHelperSector
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $contenu
 * @property string|null $txt_intro
 * @property string|null $txt_kpi
 * @property int $parent_id
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sector> $children
 * @property-read int|null $children_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector isRoot()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereContenu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereTxtIntro($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereTxtKpi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sector whereUpdatedAt($value)
 */
	class Sector extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin IdeHelperSupplier
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $incoming_email
 * @property string|null $incoming_email_title_filter
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $country
 * @property string|null $city
 * @property string|null $memo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupplierInvoice> $invoices
 * @property-read int|null $invoices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereIncomingEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereIncomingEmailTitleFilter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereUpdatedAt($value)
 */
	class Supplier extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin IdeHelperSupplierInvoice
 * @property int $id
 * @property int $supplier_id
 * @property string|null $invoice_number
 * @property string $invoice_at
 * @property string|null $currency
 * @property string|null $total_ht
 * @property int $has_tva
 * @property string|null $tx_tva
 * @property string|null $tva
 * @property string|null $total_ttc
 * @property string $status
 * @property string|null $notes
 * @property string|null $sharepoint_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $invoice_at_my
 * @property string|null $invoice_at_qy
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\Supplier $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereHasTva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereInvoiceAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereInvoiceAtMy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereInvoiceAtQy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereSharepointPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereTotalHt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereTotalTtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereTva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereTxTva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierInvoice whereUpdatedAt($value)
 */
	class SupplierInvoice extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * @mixin IdeHelperUser
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $timezone
 * @property string $locale
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $locale_display_name
 * @property-read string $timezone_display_name
 * @property-read \App\Models\MsgUserDraft|null $msgUserDraft
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

