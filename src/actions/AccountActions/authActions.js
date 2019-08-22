import axios from "axios";
import { GET_ERRORS, SET_CURRENT_USER } from "../../actions/TypesAndFields/actionTypes";
import authToken from "../../utils/authToken";
import jwt_decode from "jwt-decode";

const baseUrl = process.env.REACT_APP_BASE_URL;

/**
 * @param {Posting User Data} userData 
 * @param {go to the appropriate response page} history 
 */
export const signupUser = (userData, history) => dispatch => {

  axios
    .post(`${baseUrl}/users/signup`, userData)
    .then(res => history.push("/login"))
    .catch(err =>
      dispatch({
        type: GET_ERRORS,
        payload: err.response.data
      })
    );
};

/**  
 * 
 * @param {Grabbing User Data and setting token} userData 
 */
export const userLogin = userData => dispatch => {
  axios
    .post(`${baseUrl}/auth`, userData)
    .then(res => {
     
      
      const { token } = res.data; 
      // = token
      // SET TOKEN TO LOCAL STORAGE
      localStorage.setItem("jwtToken", res.data);
      // SET TO AUTH HEADER
      authToken(token);
      // DECODE TOKEN TO GET USER DATA
      const decoded = jwt_decode(res.data);
      // SET CURRENT USER
      dispatch(setCurrentUser(decoded));
    })
    .catch(err => {
      dispatch({
        type: GET_ERRORS,
        payload: err
      })
    });
};


/**
 * 
 * @param {func to log in user} decoded 
 */
export const setCurrentUser = decoded => {
  return {
    type: SET_CURRENT_USER,
    payload: decoded
  };
};

/**
 * @param {logging out user session} dispatch
 */
export const logoutUser = () => dispatch => {
  // remove token from local storage
  localStorage.removeItem("jwtToken");
  //remove auth header for future requests
  authToken(false);
  // set current use to {} wich will set isAuthenticated to false
  dispatch(setCurrentUser({}));
};
