@extends('crimeinvestigation.layouts.default')
@section('content')
<div class="controller">
		<div class="strips_bg"></div>
		<div class="maginfy_strip"></div>
	</div>
	
	<div class="main_screen mansion">
		<div class="container">
			 <div class="d-flex flex-column flex-md-row position-relative book_cover">
                 @include('crimeinvestigation.includes.menu')
				<aside class="book_lft">
					<div class="cover_lft"><img src="{{ asset( 'assets/crime_investigation/images/book_page_lft.png' ) }}" alt="" class="img-fluid"/></div>
					<div class="sticky_note_bottom"><img src="{{ asset( 'assets/crime_investigation/images/slices/sticky_note.png' ) }}" alt="Help Note" class="img-fluid" /></div>					
				</aside>
				<aside class="book_rht">
					<div class="sticky_note_top"><img src="{{ asset( 'assets/crime_investigation/images/slices/sticky_note.png' ) }}" alt="Help Note" class="img-fluid" /></div>
					<div class="cover_rht"><img src="{{ asset( 'assets/crime_investigation/images/book_page_rht.png' ) }}" alt="" class="img-fluid" /></div>					
				</aside>				 
				<div class="mansion_map">
					<div class="map_view shadow">
						@if( $mansionSeen )
						<img src="{{ asset( 'assets/crime_investigation/images/gallery/map_result.jpg' ) }}" alt="" class="img-fluid">
						@else
						<img src="{{ asset( 'assets/crime_investigation/images/gallery/map.png' ) }}" alt="" class="img-fluid">
						@endif
					</div>
					<div class="access_button">
						<div class="row align-items-end justify-content-end">
							@if( $mansionSeen )
							<a href="javascript:void(0);" data-toggle="modal" data-target="#found_corridor" class="contact_btn">			Secret <br/>Corridor Details
							</a>
							<a href="javascript:void(0);" class="contact_btn secret_photos">
								Photos of the <br/>secret Corridor
							</a>
							@endif
							<a href="javascript:void(0);" data-bit="{{ $bit }}" data-url="{{ route('crimeinvestigation.access_security_camra',$encId) }}" class="access-to-security-cam contact_btn">
								Access to<br/> Security Camera
							</a>						
						</div>
					</div>
				</div>
			 </div>
		</div>		
	</div>
@stop
@push( 'after_scripts' )
<script src="{{ asset( 'assets/crime_investigation/js/pages/mansion.js' ) }}"></script>
<script>
var CIVars = {
		gameMinutes	:	{{ $gameMinutes }},
		gameSeconds	:	{{ $gameSeconds }},
		hintMinutes	:	{{ $hintMinutes }},
		hintSeconds	:	{{ $hintSeconds }},
		encId		:	"{{$encId}}"
	};
</script>
@endpush