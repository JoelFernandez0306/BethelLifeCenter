import React, { Component } from 'react';
import { Link } from 'react-router-dom';
//HEAD
import { exportAllDeclaration } from '@babel/types';


class Navbar extends Component {
constructor(props) {
    super (props);
    this.state={};
    this.handleScroll = 
    this.handleScroll.bind(this);
}
handleScroll() {
    this.setState({scroll: window.scrollY});
}
componentDidMount() {
    const el = document.querySelector('nav');
    this.setState({top: el.offsetTop, height:
    exportAllDeclaration.offsetHeight});
    window.addEventListener('scroll', 
    this.handleScroll);
}
componentDidUpdate(){
    this.state.scroll > this.state.top ?
    document.body.style.paddingTop = 
    `${this.state.height}px` :
    document.body.style.paddingTop = 0;
} 
    render() {
        return (
            
            <nav className={this.state.scroll >
            this.state.top ? 'fixed-nav' : ''}>
                <ul>
                    <li className=""><Link to="/">                     
                    <h3 id='Logo'>Bethel Life Center 
                    <img src='favicon.ico'></img></h3>
                    </Link> 
                    </li>
                    <li><Link to="/about"><h5 id="aboutNav">ABOUT US </h5></Link></li> 
                    <li><Link to="/contact"><h5 id="contactNav">CONTACT US </h5></Link></li>    
                    <li><Link to="/events"><h5 id="eventsNav">EVENTS </h5></Link></li>
                    <li><Link to="/calendar"><h5 id="calendarNav">CALENDAR</h5></Link></li>     
                </ul>
            </nav>
        
        );
    }
}

export default Navbar;