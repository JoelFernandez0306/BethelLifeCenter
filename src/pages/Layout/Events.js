import React, { Component } from 'react';

class Events extends Component {
    render() {
        return (
            <div className='Events'>
                <br />
                <br />
                <br />
                {/* <div className="ui fluid segment"> */}
                    <div className='container-fluid'>
                        <div className='row'>
                            <div className='col-sm-12'>
                                <h1 className="ui header">OUR EVENTS</h1>
                                    <p id="p">
                                        <h4>Tuesday is Prayer Service</h4>
                                        <br/>
                                    Every Tuesday is Prayer Night
                                    Time: 7:30PM to 8:30PM
                                    Join us as we Pray together
                                    </p>
                            </div>
                            <div className='col-sm-12'>
                                    <p id="p">
                                        <h4>Wednesday Bread Pantry</h4>
                                    Every Wednesday we open the doors of the Church to give away bread that has been donated to us by Panera Bread. This is open to everyone.
                                    </p>
                            </div>
                            <div className='col-sm-12'></div>
                                    <p id="p">
                                        <h4>Worship & Praise Service</h4>
                                    Every Friday is our
                                    Worship and Praise Service.
                                    Time: 7:30PM
                                    Everyone is welcomed
                                    </p>
                            </div>
                            <div className='col-sm-12'>        
                                    <p id="p">
                                        <h4>Spring Village at Pocono Service</h4>
                                    The Third Sunday of every month we visit Spring Village at Pocono to bring them a Worship Service
                                    Time: 2:30PM
                                    Address:  329 E Brown St.,
                                    East Stroudsburg, PA 18301
                                    </p>
                            </div>
                            <div className='col-sm-12'>        
                                    <p id="p">
                                        <h4>Children Service</h4>
                                    The First Friday of every month is dedicated for our Children. We have a service for the children.
                                    Time: 7:30PM
                                    All children are welcomed.
                                    </p>
                            </div>
                            <div className='col-sm-12'>
                                    <p id="p">
                                        <h4>Special Events</h4>
                                    See below for all our special monthly Events
                                    </p>
                            </div>
                        </div>
                    </div>
                // </div>

            
        );
    }
}

export default Events;