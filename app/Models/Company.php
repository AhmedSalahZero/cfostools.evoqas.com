<?php

namespace App\Models;

use App\Interfaces\Models\IHaveIdentifier;
use App\Interfaces\Models\IHaveName;
use App\Traits\HasBasicStoreRequest;
use App\Traits\StaticBoot;
// use AppModels\Interfaces\Models\IHaveCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Str;

/**
 * App\Models\Company
 *
 * @property int $id
 * @property array $name
 * @property string $sub_of
 * @property string $type
 * @property int $users_number
 * @property int|null $companies_number
 * @property int|null $updated_by
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|Company[] $RevenueBusinessLines
 * @property-read int|null $revenue_business_lines_count
 * @property-read mixed $branches_with_sections
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection|\Spatie\MediaLibrary\MediaCollections\Models\Media[] $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Company[] $subCompanies
 * @property-read int|null $sub_companies_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCompaniesNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereSubOf($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUsersNumber($value)
 * @mixin \Eloquent
 */
class Company extends Model implements HasMedia , IHaveIdentifier , IHaveName
{
    use
    //  SoftDeletes,
    StaticBoot,InteractsWithMedia,HasBasicStoreRequest;
    protected $guarded = [];
    protected $casts = ['name' => 'array'];
    public function getIdentifier():int|string
    {
        return $this->{$this->getRouteKeyName()};
    }
    public function getId()
    {
        return $this->getIdentifier();
    }
    public function users()
    {
        return $this->belongsToMany(User::class, 'companies_users');
    }
    public function subCompanies()
    {
        return $this->hasMany(Company::class, 'sub_of');
    }
    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
    public function getBranchesWithSectionsAttribute()
    {
        $branches = [];
        foreach ($this->branches as  $branch) {
            @count($branch->sections) == 0 ?: array_push($branches,$branch);
        }


        return $branches;
    }

    public function exportableModelFields($modelName)
    {
        return $this->hasOne(CustomizedFieldsExportation::class)->where('model_name',$modelName);
    }
    public function RevenueBusinessLines():HasMany
    {
        return $this->hasMany(Company::class ,'company_id','id');
    }
    public function getName():string{
        return $this->name[App()->getLocale()];
    }
	public function roomNames():HasMany
	{
		return $this->hasMany(RoomName::class,'company_id','id');
	}    
	public function foodNames():HasMany
	{
		return $this->hasMany(FoodName::class,'company_id','id');
	}  
	public function insertBasicSelects()
	{
		$this->insertBasicRooms();
		$this->insertBasicFoods();
		$this->insertBasicCasinos();
		$this->insertBasicMeetings();
		$this->insertBasicOthers();
	}
	/**
	 * * start rooms 
	 */
	public function getRoomExceptTotal()
	{
		$firstRoom = $this->getTotalRoomId();
		return $this->roomNames->where('id','!=',$firstRoom);
	}
	public function getTotalRoomId()
	{
		 return getIdFromRowsWithTotal($this->roomNames->sortBy('id'));

	}
	public  function insertBasicRooms()
	{

			$rooms = [
				0=> [
			'title'=>__('Total Rooms'),
			'value'=>0
		],
		1 => [
			'title' => __('Standard Room'),
			'value' => 1
		],
		2=> [
			'title' => __('Standard Single Room'),
			'value' => 2
		],
		3=> [
			'title' => __('Standard Double Room'),
			'value' => 3
		],
		4 => [
			'title' => __('Deluxe Room'),
			'value' => 4
		],
		5 => [
			'title' => __('Deluxe Single Room'),
			'value' => 5
		],
		6 => [
			'title' => __('Deluxe Double Room'),
			'value' => 6
		],
		7 => [
			'title' => __('Premier Room'),
			'value' => 7
		],
		8 => [
			'title' => __('Premier Single Room'),
			'value' => 8
		],
		9 => [
			'title' => __('Premier Double Room'),
			'value' => 9
		],
		10 => [
			'title' => __('Junior Suite'),
			'value' => 10
		],
		11 => [
			'title' => __('Regular Suite'),
			'value' => 11
		],
		12 => [
			'title' => __('Presidential Suite'),
			'value' => 12
		],
		13 => [
			'title' => __('Royal Suite'),
			'value' => 13
		]
		];
		foreach($rooms as $roomArr){
			DB::table('room_names')->insert([
				'title'=>$roomArr['title'],
				'company_id'=>$this->id ,
				'is_active'=>1 ,
			]);
		}
		
		
	}
	
	/**
	 * * end rooms
	 */
	
	 
	 /**
	 * * start foods 
	 */
	public function getFoodExceptTotal()
	{
		$firstFood = $this->getTotalFoodId();
		return $this->foodNames->where('id','!=',$firstFood);
	}
	public function getTotalFoodId()
	{
		 return getIdFromRowsWithTotal($this->foodNames->sortBy('id'));
	}
	public  function insertBasicFoods()
	{

			$foods = [
		0 => [
			'title' => __('Total F&B Facility'),
			'value' => 0
		],
		1 => [
			'title' => __('Restaurant'),
			'value' => 1
		],
		
		2 => [
			'title' => __('Lobby Lounge & Bar'),
			'value' => 2
		],
		3 => [
			'title' => __('Café'),
			'value' => 3
		]
		
	];
		foreach($foods as $foodArr){
			DB::table('food_names')->insert([
				'title'=>$foodArr['title'],
				'company_id'=>$this->id ,
				'is_active'=>1 ,
			]);
		}
		
		
	}
	
	/**
	 * * end foods
	 */
	 /**
	 * * start casinos 
	 */
	
	 public function casinoNames():HasMany
	{
		return $this->hasMany(CasinoName::class,'company_id','id');
	}  
	
	public function getCasinoExceptTotal()
	{
		$firstId = $this->getTotalCasinoId();
		return $this->casinoNames->where('id','!=',$firstId);
	}
	public function getTotalCasinoId()
	{
		return getIdFromRowsWithTotal($this->casinoNames->sortBy('id'));
	}
	public  function insertBasicCasinos()
	{

			$items = [
		0 => [
			'title' => __('Total Gaming Facilities'),
			'value' => 0
		],
		1 => [
			'title' => __('Large Gaming Hall'),
			'value' => 1
		],
		2 => [
			'title' => __('Small Gaming Hall'),
			'value' => 2
		]
	];
		foreach($items as $item){
			DB::table('casino_names')->insert([
				'title'=>$item['title'],
				'company_id'=>$this->id ,
				'is_active'=>1 ,
			]);
		}
		
		
	}
	
	/**
	 * * end casino
	 */
	
	 
	  /**
	 * * start meetings 
	 */
	
	 public function meetingNames():HasMany
	{
		return $this->hasMany(MeetingName::class,'company_id','id');
	}  
	
	public function getMeetingExceptTotal()
	{
		$firstId = $this->getTotalMeetingId();
		return $this->meetingNames->where('id','!=',$firstId);
	}
	public function getTotalMeetingId()
	{
		return getIdFromRowsWithTotal($this->meetingNames->sortBy('id'));

	}
	public  function insertBasicMeetings()
	{

			$items = [
		0 => [
			'title' => __('Total Meeting Spaces Facilities'),
			'value' => 0
		],
		1 => [
			'title' => __('Wedding Halls'),
			'value' => 1
		],
		2 => [
			'title' => __('Meeting Rooms'),
			'value' => 2
		],

	];
		foreach($items as $item){
			DB::table('meeting_names')->insert([
				'title'=>$item['title'],
				'company_id'=>$this->id ,
				'is_active'=>1 ,
			]);
		}
		
		
	}
	
	/**
	 * * end meeting
	 */
	
	 
	  /**
	 * * start others 
	 */
	
	 public function otherNames():HasMany
	{
		return $this->hasMany(OtherName::class,'company_id','id');
	}  
	
	public function getOtherExceptTotal()
	{
		$firstId = $this->getTotalOtherId();
		return $this->otherNames->where('id','!=',$firstId);
	}
	public function getTotalOtherId()
	{
		return getIdFromRowsWithTotal($this->otherNames->sortBy('id'));
	}
	public  function insertBasicOthers()
	{

			$items = [
		0 => [
			'title' => __('Total Other Revenues Facilities'),
			'value' => 0
		],
		1 => [
			'title' => __('GYM & SPA'),
			'value' => 1
		],
		2 => [
			'title' => __('Laundry'),
			'value' => 2
		],
		3 => [
			'title' => __('Commercial Shops'),
			'value' => 3
		]
	];
		foreach($items as $item){
			DB::table('other_names')->insert([
				'title'=>$item['title'],
				'company_id'=>$this->id ,
				'is_active'=>1 ,
			]);
		}
		
		
	}
	
	/**
	 * * end others
	 */
	
	
	
}
