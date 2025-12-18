// queryAllAppeals.js
const { Gateway, Wallets } = require('fabric-network');
const path = require('path');
const fs = require('fs');

async function queryAllAppeals(adminID) {
    try {
        // Load connection profile
        const ccpPath = path.resolve(__dirname, 'network', 'connection-org1.json');
        const ccp = JSON.parse(fs.readFileSync(ccpPath, 'utf8'));

        // Setup wallet
        const walletPath = path.join(process.cwd(), 'wallet');
        const wallet = await Wallets.newFileSystemWallet(walletPath);

        // Ensure admin identity exists in wallet
        const identity = await wallet.get(adminID);
        if (!identity) {
            console.error(`Admin identity ${adminID} not found in wallet.`);
            return []; // Return empty array instead of throwing error
        }

        // Create a new gateway for connecting to our peer node.
        const gateway = new Gateway();
        await gateway.connect(ccp, { wallet, identity: adminID, discovery: { enabled: true, asLocalhost: true } });

        // Get the network (channel) our contract is deployed to.
        const network = await gateway.getNetwork('mychannel');

        // Get the contract from the network.
        const contract = network.getContract('trafficviolation');

        // Evaluate the transaction (chaincode function: QueryAllAppeals)
        const result = await contract.evaluateTransaction('QueryAllAppeals');
        
        // Disconnect from the gateway
        await gateway.disconnect();
        
        return JSON.parse(result.toString());
    } catch (error) {
        throw error;
    }
}

module.exports = { queryAllAppeals };