import React, { Component } from 'react';
import { BrowserRouter, Switch, Route } from 'react-router-dom';
import {Provider} from 'react-redux';
import store from './store';
import './index.css';
import {
    Home,
    About,
    Contact,
    Events,
    Calendar
} from './pages';
import Navbar from './pages/Layout/Navbar';
import Footer from './pages/Layout/Footer';

class App extends Component {
    render() {
        return (
            <Provider store = {store}>
            <BrowserRouter>
                <div className='App'>
                    <Navbar />
                    <Switch>
                        <Route exact path='/' component={Home} />
                        <Route path='/Contact' component={Contact} />
                        <Route path='/About' component={About} />
                        <Route path='/Events' component={Events} />
                        <Route path='/Calendar' component={Calendar} />
                        <Route path='/Footer' component={Footer} />
                        
                    </Switch>
                    <Footer/>
                </div>
            </BrowserRouter>
            </Provider>

        );
    }
}

export default App;
