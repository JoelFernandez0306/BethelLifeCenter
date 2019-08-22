import React, { Component } from 'react';
import { Form, FormGroup, Input, Label, Button } from 'reactstrap';
import axios from 'axios';
class Contact extends Component {
    constructor(props) {
        super(props)
        this.state = {
            firstName: '',
            lastName: '',
            email: '',
            message: ''
        }
        this.handleChange = this.handleChange.bind(this)
        this.handleSubmit = this.handleSubmit.bind(this)
    }
    handleChange = (e) => {
        this.setState({
          [e.target.name]: e.target.value
        })
      } 
    handleSubmit(){
        const {firstName, lastName, email, message
          } = this.state;
        return axios.post('http://localhost:3001/contacts', {
          firstName,
          lastName,
          email,
          message
        });
      }
      onSubmit(e){
        e.preventDefault();
    }
    // handleChange = e => {
    //     this.setState({ [e.target.name]: e.target.value })
    // }
    render() {
        return (
         <main>
             <br/>  
            
            <div className='form-area'>
              
                <h4>Contact Us</h4>
            <Form id='contactStyle' onSubmit={this.handleChange} style={{ width: 'auto' }}>
                
                <FormGroup >

                    <Label for='firstName'>First Name: </Label>
                    <Input 
                        type='text'
                        name='firstName'
                        onChange={this.handleChange} />
                </FormGroup>

                <FormGroup>
                    <Label for='lastName'>Last Name:</Label>
                    <Input
                        type='text'
                        name='lastName'
                        onChange={this.handleChange} />
                </FormGroup>
                <FormGroup>
                    <Label for='email'>Email:</Label>
                    <Input
                        type='text'
                        name='email'
                        onChange={this.handleChange} />
                </FormGroup>
                <FormGroup>
                    <Label for='message'>Message:</Label>
                    <Input
                        type='textarea'
                        name='message'
                        onChange={this.handleChange} />
                </FormGroup>
                <FormGroup ref="form" onSubmit={this.handleSubmit}>
            <Button type="submit">Submit</Button>
          </FormGroup>
            </Form>
            </div>
            </main>
        );
    }
}

export default Contact;