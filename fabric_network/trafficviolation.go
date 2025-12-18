package main

import (
	"encoding/json"
	"fmt"

	"github.com/hyperledger/fabric-chaincode-go/pkg/cid"
	"github.com/hyperledger/fabric-contract-api-go/contractapi"
)

// TrafficViolationChaincode implements the fabric-contract-api-go programming model
type TrafficViolationChaincode struct {
	contractapi.Contract
}

// Violation defines the structure of a traffic violation record in CouchDB.
type Violation struct {
	ViolationID        string `json:"violationID"`
	ViolationType      string `json:"violationType"`
	Location           string `json:"location"`
	PenaltyAmount      int    `json:"penaltyAmount"`
	Timestamp          string `json:"timestamp"`
	LicensePlateNumber string `json:"licensePlateNumber"`
	Image              string `json:"image"`
	Remark             string `json:"remark"`
	PaymentStatus      bool   `json:"paymentStatus"`
	ViolationStatus    bool   `json:"violationStatus"`
	DriverID           string `json:"driverID"`
	AdminID            string `json:"adminID"`
	ObjectType         string `json:"objectType"`
}

// User defines the structure of a registered user (driver or admin)
type User struct {
	UserID string `json:"userID"`
	Role   string `json:"role"`
}

type Appeal struct {
	AppealID    string `json:"appealID"`
	ViolationID string `json:"violationID"`
	DriverID    string `json:"driverID"`
	AppealText  string `json:"appealText"`
	Evidence    string `json:"evidence"`
	Timestamp   string `json:"timestamp"`
	Status      string `json:"status"`
	ObjectType  string `json:"objectType"`
}

// CreateViolation adds a new violation record to the ledger.
// Only an admin (with attribute role=admin) is allowed to create a violation.
func (t *TrafficViolationChaincode) CreateViolation(ctx contractapi.TransactionContextInterface,
	violationID string, driverID string, violationType string, location string, penaltyAmount int,
	timestamp string, licensePlateNumber string, image string, remark string, paymentStatus bool, violationStatus bool) error {

	// Ensure the invoker is an admin.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role != "admin" {
		return fmt.Errorf("only admin can create violation")
	}

	// Get admin ID from client identity.
	adminID, err := cid.GetID(ctx.GetStub())
	if err != nil {
		return fmt.Errorf("failed to get admin identity: %v", err)
	}

	// Check if the violation already exists.
	existing, err := ctx.GetStub().GetState(violationID)
	if err != nil {
		return fmt.Errorf("failed to read from world state: %v", err)
	}
	if existing != nil {
		return fmt.Errorf("violation %s already exists", violationID)
	}

	violation := Violation{
		ViolationID:        violationID,
		ViolationType:      violationType,
		Location:           location,
		PenaltyAmount:      penaltyAmount,
		Timestamp:          timestamp,
		LicensePlateNumber: licensePlateNumber,
		Image:              image,
		Remark:             remark,
		PaymentStatus:      paymentStatus,
		ViolationStatus:    violationStatus,
		DriverID:           driverID,
		AdminID:            adminID,
		ObjectType:         "violation",
	}

	violationBytes, err := json.Marshal(violation)
	if err != nil {
		return fmt.Errorf("failed to marshal violation: %v", err)
	}

	return ctx.GetStub().PutState(violationID, violationBytes)
}

// DeleteViolation removes a violation record from the ledger.
// Only an admin (with attribute role=admin) is allowed to delete a violation.
func (t *TrafficViolationChaincode) DeleteViolation(ctx contractapi.TransactionContextInterface, violationID string) error {
	// Ensure the invoker is an admin.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role != "admin" {
		return fmt.Errorf("only admin can delete violation")
	}

	// Check if the violation exists.
	existing, err := ctx.GetStub().GetState(violationID)
	if err != nil {
		return fmt.Errorf("failed to read from world state: %v", err)
	}
	if existing == nil {
		return fmt.Errorf("violation %s does not exist", violationID)
	}

	// Delete the violation from the world state.
	return ctx.GetStub().DelState(violationID)
}

// DeleteAppeal removes an appeal record from the ledger.
// Only an admin (with attribute role=admin) is allowed to delete an appeal.
func (t *TrafficViolationChaincode) DeleteAppeal(ctx contractapi.TransactionContextInterface, appealID string) error {
    // Ensure the invoker is an admin.
    role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
    if err != nil {
        return fmt.Errorf("failed to get attribute 'role': %v", err)
    }
    if !found || role != "admin" {
        return fmt.Errorf("only admin can delete appeal")
    }

    // Check if the appeal exists.
    existing, err := ctx.GetStub().GetState(appealID)
    if err != nil {
        return fmt.Errorf("failed to read from world state: %v", err)
    }
    if existing == nil {
        return fmt.Errorf("appeal %s does not exist", appealID)
    }

    // Delete the appeal from the world state.
    return ctx.GetStub().DelState(appealID)
}

// UpdateViolation allows an admin to update the violation status and payment status.
// Only an admin (with attribute role=admin) is allowed to update a violation.
func (t *TrafficViolationChaincode) UpdateViolation(ctx contractapi.TransactionContextInterface, violationID string, newPaymentStatus bool, newViolationStatus bool) error {
	// Ensure the invoker is an admin.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role != "admin" {
		return fmt.Errorf("access denied: only admin can update violation status")
	}

	// Get the existing violation record.
	violationBytes, err := ctx.GetStub().GetState(violationID)
	if err != nil {
		return fmt.Errorf("failed to read from world state: %v", err)
	}
	if violationBytes == nil {
		return fmt.Errorf("violation %s does not exist", violationID)
	}

	var violation Violation
	err = json.Unmarshal(violationBytes, &violation)
	if err != nil {
		return fmt.Errorf("failed to unmarshal violation: %v", err)
	}

	// Update the paymentStatus and violationStatus.
	violation.PaymentStatus = newPaymentStatus
	violation.ViolationStatus = newViolationStatus

	// Marshal the updated violation record.
	updatedViolationBytes, err := json.Marshal(violation)
	if err != nil {
		return fmt.Errorf("failed to marshal updated violation: %v", err)
	}

	// Write the updated record back to the world state.
	return ctx.GetStub().PutState(violationID, updatedViolationBytes)
}

// QueryViolation retrieves a specific violation record by its ID.
// If the caller is not an admin, they can only query records that belong to them.
func (t *TrafficViolationChaincode) QueryViolation(ctx contractapi.TransactionContextInterface, violationID string) (*Violation, error) {
	violationBytes, err := ctx.GetStub().GetState(violationID)
	if err != nil {
		return nil, fmt.Errorf("failed to read from world state: %v", err)
	}
	if violationBytes == nil {
		return nil, fmt.Errorf("violation %s does not exist", violationID)
	}

	var violation Violation
	err = json.Unmarshal(violationBytes, &violation)
	if err != nil {
		return nil, fmt.Errorf("failed to unmarshal violation: %v", err)
	}

	// Get the caller's identity.
	clientID, err := cid.GetID(ctx.GetStub())
	if err != nil {
		return nil, fmt.Errorf("failed to get client identity: %v", err)
	}

	// Check the invoker's role.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return nil, fmt.Errorf("failed to get attribute 'role': %v", err)
	}

	// If not an admin, ensure that the violation belongs to the caller.
	if !found || role != "admin" {
		if violation.DriverID != clientID {
			return nil, fmt.Errorf("access denied: violation does not belong to you")
		}
	}

	return &violation, nil
}

// QueryAllViolations returns all violation records.
// Only an admin is allowed to use this function.
func (t *TrafficViolationChaincode) QueryAllViolations(ctx contractapi.TransactionContextInterface) ([]*Violation, error) {
	// Check that the invoker is an admin.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return nil, fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role != "admin" {
		return nil, fmt.Errorf("access denied: only admin can query all violations")
	}

	queryString := "{\"selector\":{\"objectType\":\"violation\"}}"
	resultsIterator, err := ctx.GetStub().GetQueryResult(queryString)
	if err != nil {
		return nil, err
	}
	defer resultsIterator.Close()

	var violations []*Violation
	for resultsIterator.HasNext() {
		queryResponse, err := resultsIterator.Next()
		if err != nil {
			return nil, err
		}
		var violation Violation
		err = json.Unmarshal(queryResponse.Value, &violation)
		if err != nil {
			return nil, err
		}
		violations = append(violations, &violation)
	}
	return violations, nil
}

// QueryMyViolations returns all violation records associated with the invoker (driver).
// This function can be used by a driver to view only their own records.
func (t *TrafficViolationChaincode) QueryMyViolations(ctx contractapi.TransactionContextInterface) ([]*Violation, error) {
	// Get the caller's certificate-based identity.
	clientID, err := cid.GetID(ctx.GetStub())
	if err != nil {
		return nil, fmt.Errorf("failed to get client identity: %v", err)
	}

	// Query records where driverID matches the caller's ID.
	queryString := fmt.Sprintf("{\"selector\":{\"objectType\":\"violation\", \"driverID\":\"%s\"}}", clientID)
	resultsIterator, err := ctx.GetStub().GetQueryResult(queryString)
	if err != nil {
		return nil, err
	}
	defer resultsIterator.Close()

	var violations []*Violation
	for resultsIterator.HasNext() {
		queryResponse, err := resultsIterator.Next()
		if err != nil {
			return nil, err
		}
		var violation Violation
		err = json.Unmarshal(queryResponse.Value, &violation)
		if err != nil {
			return nil, err
		}
		violations = append(violations, &violation)
	}
	return violations, nil
}

// GetClientID returns the caller's identity as a string.
func (t *TrafficViolationChaincode) GetClientID(ctx contractapi.TransactionContextInterface) (string, error) {
	clientID, err := cid.GetID(ctx.GetStub())
	if err != nil {
		return "", fmt.Errorf("failed to get client identity: %v", err)
	}
	return clientID, nil
}

// GetClientRole returns the role attribute of the caller.
func (t *TrafficViolationChaincode) GetClientRole(ctx contractapi.TransactionContextInterface) (string, error) {
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return "", fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found {
		return "role not found", nil
	}
	return role, nil
}

// CreateUser registers a new user (driver or admin) in the ledger.
func (t *TrafficViolationChaincode) CreateUser(ctx contractapi.TransactionContextInterface, userID string, role string) error {
	// Check if the user already exists.
	existing, err := ctx.GetStub().GetState(userID)
	if err != nil {
		return fmt.Errorf("failed to read from world state: %v", err)
	}
	if existing != nil {
		return fmt.Errorf("user %s already exists", userID)
	}

	user := User{
		UserID: userID,
		Role:   role,
	}

	userBytes, err := json.Marshal(user)
	if err != nil {
		return fmt.Errorf("failed to marshal user: %v", err)
	}

	return ctx.GetStub().PutState(userID, userBytes)
}

// SubmitAppeal allows a driver to submit an appeal for a violation.
// It verifies the caller is a driver and that the violation belongs to them.
func (t *TrafficViolationChaincode) SubmitAppeal(ctx contractapi.TransactionContextInterface,
	appealID string, violationID string, appealText string, evidence string, timestamp string) error {

	// Ensure the invoker is a driver (and not an admin).
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role == "admin" {
		return fmt.Errorf("only drivers can submit appeals")
	}

	// Get driver identity.
	driverID, err := cid.GetID(ctx.GetStub())
	if err != nil {
		return fmt.Errorf("failed to get driver identity: %v", err)
	}

	// Check if the referenced violation exists.
	violationBytes, err := ctx.GetStub().GetState(violationID)
	if err != nil {
		return fmt.Errorf("failed to read violation: %v", err)
	}
	if violationBytes == nil {
		return fmt.Errorf("violation %s does not exist", violationID)
	}
	var violation Violation
	err = json.Unmarshal(violationBytes, &violation)
	if err != nil {
		return fmt.Errorf("failed to unmarshal violation: %v", err)
	}
	// Ensure the violation belongs to the driver.
	if violation.DriverID != driverID {
		return fmt.Errorf("access denied: violation does not belong to you")
	}

	// Create the appeal record.
	appeal := Appeal{
		AppealID:    appealID,
		ViolationID: violationID,
		DriverID:    driverID,
		AppealText:  appealText,
		Evidence:    evidence,
		Timestamp:   timestamp,
		Status:      "Pending",
		ObjectType:  "appeal",
	}

	appealBytes, err := json.Marshal(appeal)
	if err != nil {
		return fmt.Errorf("failed to marshal appeal: %v", err)
	}

	return ctx.GetStub().PutState(appealID, appealBytes)
}

// QueryMyAppeals returns all appeal records associated with the driver (caller).
func (t *TrafficViolationChaincode) QueryMyAppeals(ctx contractapi.TransactionContextInterface) ([]*Appeal, error) {
	// Get the driver's identity.
	driverID, err := cid.GetID(ctx.GetStub())
	if err != nil {
		return nil, fmt.Errorf("failed to get driver identity: %v", err)
	}

	// Build a CouchDB query to select appeals for this driver.
	queryString := fmt.Sprintf("{\"selector\":{\"objectType\":\"appeal\", \"driverID\":\"%s\"}}", driverID)
	resultsIterator, err := ctx.GetStub().GetQueryResult(queryString)
	if err != nil {
		return nil, err
	}
	defer resultsIterator.Close()

	var appeals []*Appeal
	for resultsIterator.HasNext() {
		queryResponse, err := resultsIterator.Next()
		if err != nil {
			return nil, err
		}
		var appeal Appeal
		err = json.Unmarshal(queryResponse.Value, &appeal)
		if err != nil {
			return nil, err
		}
		appeals = append(appeals, &appeal)
	}
	return appeals, nil
}

// QueryAllAppeals returns all appeal records.
// Only an admin is allowed to use this function.
func (t *TrafficViolationChaincode) QueryAllAppeals(ctx contractapi.TransactionContextInterface) ([]*Appeal, error) {
	// Ensure the invoker is an admin.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return nil, fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role != "admin" {
		return nil, fmt.Errorf("access denied: only admin can query all appeals")
	}

	queryString := "{\"selector\":{\"objectType\":\"appeal\"}}"
	resultsIterator, err := ctx.GetStub().GetQueryResult(queryString)
	if err != nil {
		return nil, err
	}
	defer resultsIterator.Close()

	var appeals []*Appeal
	for resultsIterator.HasNext() {
		queryResponse, err := resultsIterator.Next()
		if err != nil {
			return nil, err
		}
		var appeal Appeal
		err = json.Unmarshal(queryResponse.Value, &appeal)
		if err != nil {
			return nil, err
		}
		appeals = append(appeals, &appeal)
	}
	return appeals, nil
}

// UpdateAppealStatus allows an admin to update the status of an appeal after review.
func (t *TrafficViolationChaincode) UpdateAppealStatus(ctx contractapi.TransactionContextInterface, appealID string, newStatus string) error {
	// Ensure the invoker is an admin.
	role, found, err := cid.GetAttributeValue(ctx.GetStub(), "role")
	if err != nil {
		return fmt.Errorf("failed to get attribute 'role': %v", err)
	}
	if !found || role != "admin" {
		return fmt.Errorf("access denied: only admin can update appeal status")
	}

	// Retrieve the appeal record.
	appealBytes, err := ctx.GetStub().GetState(appealID)
	if err != nil {
		return fmt.Errorf("failed to read appeal: %v", err)
	}
	if appealBytes == nil {
		return fmt.Errorf("appeal %s does not exist", appealID)
	}

	var appeal Appeal
	err = json.Unmarshal(appealBytes, &appeal)
	if err != nil {
		return fmt.Errorf("failed to unmarshal appeal: %v", err)
	}

	// Update the appeal status.
	appeal.Status = newStatus

	updatedAppealBytes, err := json.Marshal(appeal)
	if err != nil {
		return fmt.Errorf("failed to marshal updated appeal: %v", err)
	}

	return ctx.GetStub().PutState(appealID, updatedAppealBytes)
}

func main() {
	chaincode, err := contractapi.NewChaincode(new(TrafficViolationChaincode))
	if err != nil {
		fmt.Printf("Error creating chaincode: %v\n", err)
		return
	}

	if err := chaincode.Start(); err != nil {
		fmt.Printf("Error starting chaincode: %v\n", err)
	}
}
