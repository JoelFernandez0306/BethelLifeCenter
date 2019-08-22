import React from "react";
import { MDBCarousel, MDBCarouselCaption, MDBCarouselInner, MDBCarouselItem, MDBView, MDBMask, MDBContainer } from
"mdbreact";
import {Link} from 'react-router-dom';


const CarouselPage = () => {
  return (

  <div className='LandingBG' >
    
      <MDBContainer>
      <MDBCarousel
      activeItem={1}
      length={3}
      showControls={true}
      showIndicators={false}
      className="z-depth-1"
    >
      <MDBCarouselInner>
        <MDBCarouselItem itemId="1">
          <MDBView>
          <img src={require('C:/Users/joelc/Documents/GitHub/BethelLifeCenter/src/images/Bethel Center friday night service.jpg')} alt="" style={{ width: "100%"}}/> 
           
          <MDBMask overlay="black-light" />
          </MDBView>
          <MDBCarouselCaption>
            <h3 className="h3-responsive"></h3>
            <p></p>
          </MDBCarouselCaption>
        </MDBCarouselItem>
        <MDBCarouselItem itemId="2">
          <MDBView>
            <Link to='/events'>
          <img src={require('C:/Users/joelc/Documents/GitHub/BethelLifeCenter/src/images/connect.jpg')} alt="" style={{ width: "100%"}}/> 
          <MDBMask overlay="black-strong" />
          </Link>
          </MDBView>
          <MDBCarouselCaption>
            <div id='connectHere'>
           {/* <a href='./SignUp'><h4 className="h3-responsive">Get Connected</h4></a> */}
            </div>
          <br/>
          </MDBCarouselCaption>
        </MDBCarouselItem>
        <MDBCarouselItem itemId="3">
          <MDBView>
            <Link to="/calendar">
          <img src={require('C:/Users/joelc/Documents/GitHub/BethelLifeCenter/src/images/calendar.jpg')} alt="" style={{ width: "100%"}}/> 
          </Link>
          <MDBMask overlay="black-slight" />
          </MDBView>
          <MDBCarouselCaption>
             
            <h4 className="h3-responsive">
            <p>Check out our calendar for important dates</p>
            </h4>
          </MDBCarouselCaption>
        </MDBCarouselItem>
      </MDBCarouselInner>
    </MDBCarousel>
    </MDBContainer>
 
   </div>
  );
}

export default CarouselPage;