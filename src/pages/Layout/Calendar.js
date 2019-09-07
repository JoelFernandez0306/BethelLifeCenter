import React, { Component } from 'react';

class Calendar extends Component {
    render() {
        return (
            <div className='calendar'>
                <br />
                <br />
                <br />
                <div className="ui fluid segment">
                    <h3 className="ui header">Calendar</h3>
                    <div className="weeklycal">
                    
                    </div>
                    <div className="monthlycal">
                    <iframe className="cal" src="https://calendar.google.com/calendar/embed?height=600&wkst=1&bgcolor=%23ffffff&ctz=America%2FNew_York&src=bmQwbjloZGQ4Nm5sNDQ3aWVydmg2NWs4NDhAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ&src=ZW4uY2hyaXN0aWFuI2hvbGlkYXlAZ3JvdXAudi5jYWxlbmRhci5nb29nbGUuY29t&src=ZW4udXNhI2hvbGlkYXlAZ3JvdXAudi5jYWxlbmRhci5nb29nbGUuY29t&src=ZW4uanVkYWlzbSNob2xpZGF5QGdyb3VwLnYuY2FsZW5kYXIuZ29vZ2xlLmNvbQ&color=%23F6BF26&color=%237CB342&color=%23009688&color=%230B8043"></iframe>
                    </div>
                </div>
                <br />
                <br />

            </div>
        );
    }
}

export default Calendar;