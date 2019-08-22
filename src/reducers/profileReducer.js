// import { SET_CURRENT_USER } from '../actions/types';
// import emptyValue  from '../utils/emptyValue';
// const initialstate = {
//   isAuthenticated: false,
//   user: {}
// }
// /**
//  * Setting current user and checking for validation
//  * @param {object} state 
//  * @param {action type} action 
//  */
// export default function(state = initialstate, action) {
//   switch(action.type) {
//     case SET_CURRENT_USER:
//       return {
//         ...state,
//         isAuthenticated: !emptyValue(action.payload),
//         user: action.payload
//       }
//     default:
//     return state;
//   }
// }