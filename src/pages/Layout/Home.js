import React, { Component } from 'react';
import Landing from './Landing';

class Home extends Component {
    render() {
        return (
            <div id='homeBody'>
                <br />
                <br />
                <br />            
                <div class="ui fluid segment">
                    <Landing /> 
                    <h3 class="ui header">Bethel Life Center</h3>
                     {/* <div class="ui fading segment">
                </div> */}
             </div>   
            </div>
        );
    }
}

export default Home;