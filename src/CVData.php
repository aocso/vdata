<?php

namespace dekuan\vdata;

use dekuan\vdata\CConst;
use dekuan\delib\CLib;
use Symfony\Component\HttpFoundation\Response;


class CVData
{
	protected static $g_cStaticVDataInstance;

	//
	//	constants
	//
	const SERVICE_DEFAULT_VERSION	= '1.0';		//	default service version

	//	reserved keys
	const ARR_RESERVED_KEYS		= [ 'name', 'url', 'errorid', 'errordesc', 'vdata', 'parents' ];


	//
	//	members
	//
	protected $m_cCors;


	public function __construct()
	{
		$this->m_cCors	= new CCors();
	}
	public function __destruct()
	{
	}
	static function GetInstance()
	{
		if ( is_null( self::$g_cStaticVDataInstance ) || ! isset( self::$g_cStaticVDataInstance ) )
		{
			self::$g_cStaticVDataInstance = new self();
		}
		return self::$g_cStaticVDataInstance;
	}


	//
	//	get json in virtual data format
	//
	public function GetVDataArray
	(
		$nErrorId,
		$sErrorDesc	= '',
		$arrVData	= [],
		$sVersion	= '1.0',
		$bCached	= null,
		$arrExtra	= []
	)
	{
		//
		//	nErrorId	- [in] int		error id
		//	sErrorDesc	- [in/opt] string	error description
		//	arrVData	- [in/opt] array	virtual data
		//	sVersion	- [in/opt] string	service version, default is '1.0'
		//	bCached		- [in/opt] bool		if the data come from cache
		//	arrExtra	- [in/opt] array	extra data by key-value pairs
		//	RETURN		- json array
		//
		$arrRet = [];

		if ( is_numeric( $nErrorId ) )
		{
			//	Okay
			$nErrorId	= intval( $nErrorId );
			$sErrorDesc	= ( ( is_string( $sErrorDesc ) || is_numeric( $sErrorDesc ) ) ? strval( $sErrorDesc ) : '' );
			$arrRet =
				[
					'errorid'   => $nErrorId,
					'errordesc' => $sErrorDesc,
					'vdata'     => ( is_array( $arrVData ) ? $arrVData : [] ),
				];
		}
		else
		{
			//	invalid
			$arrRet = $this->GetDefaultVDataJson();
		}

		//
		//	tell the world/client, we are version x.x.x via:
		//	2, version node of vdata
		//
		$arrRet[ 'version' ] = ( is_string( $sVersion ) && strlen( $sVersion ) > 0 ) ? $sVersion : self::SERVICE_DEFAULT_VERSION;

		//
		//	data from cache?
		//
		if ( is_bool( $bCached ) )
		{
			$arrRet['cache'] = intval( $bCached ? 1 : 0 );
		}

		//
		//	extra data
		//
		if ( CLib::IsArrayWithKeys( $arrExtra ) )
		{
			foreach ( $arrExtra as $sKey => $vValue )
			{
				if ( CLib::IsExistingString( $sKey, true ) )
				{
					//	trim and lower case
					$sKey	= strtolower( trim( $sKey ) );

					//	append the valid item to return value
					if ( ! $this->IsReservedKey( $sKey ) &&
						! array_key_exists( $sKey, $arrRet ) )
					{
						$arrRet[ $sKey ] = $vValue;
					}
				}
			}
		}

		return $arrRet;
	}

	//
	//	get json encoded string in virtual data format
	//
	public function GetVDataString
	(
		$nErrorId,
		$sErrorDesc	= '',
		$arrVData	= [],
		$sVersion	= '1.0',
		$bCached	= null,
		$arrExtra	= []
	)
	{
		//
		//	nErrorId	- [in] int		error id
		//	sErrorDesc	- [in/opt] string	error description
		//	arrVData	- [in/opt] array	virtual data
		//	sVersion	- [in/opt] string	service version, default is '1.0'
		//	bCached		- [in/opt] bool		if the data come from cache
		//	arrExtra	- [in/opt] array	extra data by key-value pairs
		//	RETURN		- encoded json string
		//
		$sRet = '';

		//	...
		$arrJson = $this->GetVDataArray( $nErrorId, $sErrorDesc, $arrVData, $sVersion, $bCached, $arrExtra );
		if ( $this->IsValidVDataJson( $arrJson ) )
		{
			$sRet = @ json_encode( $arrJson );
		}
		else
		{
			$sRet = @ json_encode( $this->GetDefaultVDataJson() );
		}

		return $sRet;
	}

	//
	//	get response object instance contains json encoded string in virtual data format
	//
	public function GetVDataResponse
	(
		$nErrorId,
		$sErrorDesc	= '',
		$arrVData	= [],
		$sVersion	= self::SERVICE_DEFAULT_VERSION,
		$bCached	= null,
		$arrExtra	= [],
		$nHttpStatus	= Response::HTTP_OK
	)
	{
		//
		//	nErrorId	- [in] int		error id
		//	sErrorDesc	- [in/opt] string	error description
		//	arrVData	- [in/opt] array	virtual data
		//	sVersion	- [in/opt] string	service version, default is '1.0'
		//	bCached		- [in/opt] bool		if the data come from cache
		//	arrExtra	- [in/opt] array	extra data by key-value pairs
		//	nHttpStatus	- [in/opt] int		HTTP response status
		//	RETURN		- Instance of Symfony\Component\HttpFoundation\Response
		//
		$cResponse	= new Response();
		$sContentJson	= '';
		$sContentType	= '';

		if ( ! is_numeric( $nHttpStatus ) )
		{
			$nHttpStatus = Response::HTTP_OK;
		}

		//
		//	send json response to client
		//
		$sContentJson	= $this->GetVDataString( $nErrorId, $sErrorDesc, $arrVData, $sVersion, $bCached, $arrExtra );

		//
		//	tell the world/client, we are version x.x.x via Content-Type of HTTP header
		//
		$sContentType	= $this->GetContentTypeWithVersion( $sVersion );

		//
		//	send response to client now
		//
		$cResponse->setContent( $sContentJson );
		$cResponse->setStatusCode( $nHttpStatus );
		$cResponse->headers->set( 'Content-Type', $sContentType );

		if ( $this->IsAllowedCorsRequest() )
		{
			//
			//	this request is allowed via Cross-Origin Resource Sharing
			//
			$cResponse->headers->set( 'Access-Control-Allow-Origin', '*' );
			//$cResponse->headers->set( 'Access-Control-Allow-Headers', '*' );
			//$cResponse->headers->set( 'Access-Control-Max-Age', 60 );
			//$cResponse->headers->set( 'Access-Control-Allow-Methods', 'POST' );
		}

		//
		//	prints the HTTP headers followed by the content
		//
		return $cResponse;
	}

	//
	//	if the variable being evaluated is a valid json in virtual data format ?
	//
	public function IsValidVDataJson( $arrJson )
	{
		return ( is_array( $arrJson ) &&
			array_key_exists( 'errorid', $arrJson ) &&
			array_key_exists( 'errordesc', $arrJson ) &&
			array_key_exists( 'vdata', $arrJson ) &&
			is_numeric( $arrJson[ 'errorid' ] ) &&
			is_string( $arrJson[ 'errordesc' ] ) &&
			is_array( $arrJson[ 'vdata' ] ) );
	}

	//
	//	if the variable being evaluated is the reserved key
	//
	public function IsReservedKey( $sKey )
	{
		$bRet	= false;

		if ( CLib::IsExistingString( $sKey, true ) )
		{
			$sKey	= strtolower( trim( $sKey ) );
			$bRet	= in_array( $sKey, self::ARR_RESERVED_KEYS );
		}

		return $bRet;
	}


	//
	//	get default json in virtual data format
	//
	public function GetDefaultVDataJson()
	{
		return [
			'errorid'	=> intval( CConst::ERROR_UNKNOWN ),
			'errordesc'	=> '',
			'vdata'		=> [],
			'version'	=> self::SERVICE_DEFAULT_VERSION
		];
	}

	//
	//	get default service version
	//
	public function GetDefaultVersion()
	{
		return self::SERVICE_DEFAULT_VERSION;
	}

	//
	//	get content type of HTTP header
	//
	public function GetContentTypeWithVersion( $sVersion )
	{
		return sprintf
		(
			"application/json; version:%s",
			( is_string( $sVersion ) && strlen( $sVersion ) > 0 ) ? $sVersion : self::SERVICE_DEFAULT_VERSION
		);
	}

	//
	//	set domains for Cross-Origin Resource Sharing
	//
	public function SetCorsDomains( $arrDomains )
	{
		return $this->m_cCors->SetCorsDomains( $arrDomains );
	}

	//
	//	is allowed cors request
	//
	public function IsAllowedCorsRequest()
	{
		return $this->m_cCors->IsAllowedCorsRequest();
	}

}