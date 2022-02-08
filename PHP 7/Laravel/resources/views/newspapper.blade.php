@extends('crimeinvestigation.layouts.default')
@section('content')
	<div class="controller">
		<div class="strips_bg"></div>
		<div class="maginfy_strip"></div>
	</div>
	
	<div class="main_screen newspapper">
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
				<ul class="d-flex flex-wrap news_clips">
					<li class="clips-item" onclick="lightbox(0)">
						<img class="img-fluid" src="{{ asset( 'assets/crime_investigation/images/gallery/news_clip1.png' ) }}" alt=""/>
					</li>
					 <li class="clips-item" onclick="lightbox(1)">
						<img class="img-fluid" src="{{ asset( 'assets/crime_investigation/images/gallery/news_clip2.png' ) }}" alt=""/>
					</li>
					<li class="clips-item" onclick="lightbox(2)">
						<img class="img-fluid" src="{{ asset( 'assets/crime_investigation/images/gallery/news_clip3.png' ) }}" alt=""/>						 	
					</li>
					<li class="clips-item" onclick="lightbox(3)">
						<img class="img-fluid" src="{{ asset( 'assets/crime_investigation/images/gallery/news_clip4.png' ) }}" alt=""/>
					</li>
					<li class="clips-item" onclick="lightbox(4)">
						<img class="img-fluid" src="{{ asset( 'assets/crime_investigation/images/gallery/news_clip5.png' ) }}" alt=""/>
					</li>
					<li class="clips-item" onclick="lightbox(5)">
						<img class="img-fluid" src="{{ asset( 'assets/crime_investigation/images/gallery/news_clip6.png' ) }}" alt=""/>
						
					</li>
				</ul>

				<div style="display:none;">
			        <div id="ninja-slider">
			            <div class="slider-inner">
			                <ul>
			                    <li class="d-flex align-items-center justify-content-center">
			                        <div class="news-article">
										<hgroup class="news_title">
											<h3>BELOVED LOCAL <span>PAINTING VANISHES</span></h3>
											<h6>San Francisco, CA  October 10, 2014</h6>
										</hgroup>
										<div class="popup_content">
											<p>San Francisco is stumped as a local treasure, the <i>Gold Rush</i> painting disappeared while on loan to the New York Museum, which was showcasing art depicting life during the Gold Rush Era of 1849 in the United States.  Authorities believe this may have happened while the painting was in transit.</p>
										</div>
										<a href="#" class="contact_btn">Contact </br>Museum</a>
			                        </div>
			                    </li>
			                    <li class="d-flex align-items-center justify-content-center">
			                        <div class="news-article">
										<hgroup class="news_title">
											<h3>PAINTING DISAPPEARS <span>FROM MUSEUM</span></h3>
											<h6>Madrid, Spain June 6, 2015</h6>
										</hgroup>
										<div class="popup_content">
											<p>Local authorities are baffled as an iconic painting of the modern art movement has disappeared. The Medley, which showcases a plethora of shapes in an awe-inspiring form, disappeared from a Madrid museum overnight. Police are reviewing surveillance and have involved Spain’s national police to assist. The painting is estimated to be worth 3 million euros.</p>
										</div>
										<a href="#" class="contact_btn">Contact </br>Museum</a>
			                        </div>
			                    </li>
			                    <li class="d-flex align-items-center justify-content-center">
			                        <div class="news-article">
										<hgroup class="news_title">
											<h3>CHERRY TREES UPROOTED <span>FROM MUSUEM IN TOKYO</span></h3>
											<h6>Tokyo, Japan March 19, 2016</h6>
										</hgroup>
										<div class="popup_content">
											<p>The most iconic modern painting of cherry trees, The Cherry Trees, has disappeared in transit to a museum in Tokyo. Police are concerned the 2014 disappearance of a painting also in transit, in the United States may be connected.</p>
										</div>
										<a href="#" class="contact_btn">Contact </br>Museum</a>
			                        </div>
			                    </li>
			                    <li class="d-flex align-items-center justify-content-center">
			                        <div class="news-article">
										<hgroup class="news_title">
											<h3>METROPOLIS <span>SKIPS TOWN</span></h3>
											<h6>Prague, Czech Republic <br/>July 6, 2017</h6>
										</hgroup>
										<div class="popup_content">
											<p>The favorite, <i>Metropolis</i>, a modern watercolor painting showcasing bright colors depicting the city of Prague has left the cities museum in what police fear is a coordinated theft of treasured paintings around the world.</p>
										</div>
										<a href="#" class="contact_btn">Contact </br>Museum</a>
			                        </div>
			                    </li>
			                    <li class="d-flex align-items-center justify-content-center">
			                        <div class="news-article">
										<hgroup class="news_title">
											<h3>BROKEN HEARTS AS <span>SCATTERED HEARTS DISAPPEARS</span></h3>
											<h6>Paris, February 21, 2019</h6>
										</hgroup>
										<div class="popup_content">
											<p>An art thief (perhaps the same one?) continued to shake the artworld as the beloved modern art piece, Scattered Hearts disappeared from a Paris Museum. Employees of the museum are being questioned as it appears to be an inside job. No wires or alarms were tripped. This marks the fifth painting to vanish in transit or be outright stolen from an art museum in 5 years.</p>
										</div>
										<a href="#" class="contact_btn">Contact </br>Museum</a>
			                        </div>
			                    </li>
			                    <li class="d-flex align-items-center justify-content-center">
			                        <div class="news-article">
										<hgroup class="news_title">
											<h3>PAINTING DISAPPEARS <span>FROM WALNUT GROVE MANSION</span></h3>
											<h6>London, January 16, 2020</h6>
										</hgroup>
										<div class="popup_content">
											<p>Modern art painting, the Black Square has disappeared from Walnut Grove Mansion during a dinner party. The Black Square was recently acquired by local business magnate, Daniel Pembroke and made its debut at a private party at Mr. Pembroke’s Walnut Grove Mansion late last evening. The Black Square, was painted by modern artist, Forester Snow, known for his bold geometric shape paintings. Concern continues to grow that the theft of multiple modern paintings disappearing across the globe are part of a coordinated heist.</p>
										</div>
										<a href="#" class="contact_btn">Contact</br> Museum</a>
			                        </div>
			                    </li>
			                </ul>
			                <div id="fsBtn" class="fs-icon" title="Expand/Close"></div>
			            </div>
			        </div>
			    </div>
			 </div>			 
		</div>		
	</div>
	<!-- End Main Screen -->
@stop
@push( 'after_scripts' )
<script src="{{ asset( 'assets/crime_investigation/js/pages/newspapper.js' ) }}"></script>
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