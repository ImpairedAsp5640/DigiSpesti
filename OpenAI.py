import os
import openai
import argparse
from dotenv import load_dotenv

def setup_api_key():
    """
    Set up the OpenAI API key from environment variables or user input.
    """
    # Try to load from .env file first
    load_dotenv()
    
    # Try multiple potential environment variable names
    api_key = os.getenv("OPENAI_API_KEY")
    
    # Check if there's a file named OPENAI_API_KEY.env
    if not api_key and os.path.exists("OPENAI_API_KEY.env"):
        with open("OPENAI_API_KEY.env", "r") as f:
            api_key = f.read().strip()
            # Set it in the environment
            os.environ["OPENAI_API_KEY"] = api_key
    
    # If no API key in environment, ask the user
    if not api_key:
        api_key = input("Please enter your OpenAI API key: ")
        # Save to environment variable for this session
        os.environ["OPENAI_API_KEY"] = api_key
    
    return api_key

def ask_openai(question, model="gpt-3.5-turbo"):
    """
    Send a question to OpenAI and get the response.
    
    Args:
        question (str): The user's question
        model (str): The OpenAI model to use
        
    Returns:
        str: The AI's response
    """
    client = openai.OpenAI()
    
    try:
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": "You are a helpful assistant."},
                {"role": "user", "content": question}
            ]
        )
        
        return response.choices[0].message.content
    
    except Exception as e:
        return f"Error: {str(e)}"

def check_api_key_validity(api_key):
    """Check if the API key is valid and has quota remaining"""
    client = openai.OpenAI(api_key=api_key)
    try:
        # Make a minimal API call to check validity
        response = client.chat.completions.create(
            model="gpt-3.5-turbo",
            messages=[{"role": "user", "content": "hello"}],
            max_tokens=5  # Minimize token usage
        )
        return True, "API key is valid"
    except openai.OpenAIError as e:
        return False, f"API key error: {str(e)}"

def main():
    # Set up argument parser
    parser = argparse.ArgumentParser(description='Terminal interface for OpenAI API')
    parser.add_argument('--model', type=str, default="gpt-3.5-turbo", 
                        help='OpenAI model to use (default: gpt-3.5-turbo)')
    parser.add_argument('--api-key', type=str, help='OpenAI API key (overrides environment variable)')
    args = parser.parse_args()
    
    # Set up API key
    if args.api_key:
        api_key = args.api_key
    else:
        api_key = setup_api_key()
    
    # Validate API key
    is_valid, message = check_api_key_validity(api_key)
    if not is_valid:
        print(f"Error: {message}")
        print("Check your OpenAI account at https://platform.openai.com/account/usage")
        print("Make sure you have sufficient credits and your API key is correct.")
        return
    
    print("API key validated successfully!")
    openai.api_key = api_key
    
    print("OpenAI Terminal Chat (type 'exit' to quit)")
    print(f"Using model: {args.model}")
    print("-" * 50)
    
    # Main chat loop
    while True:
        # Get user input
        user_question = input("\nYou: ")
        
        # Check if user wants to exit
        if user_question.lower() in ["exit", "quit", "bye"]:
            print("Goodbye!")
            break
        
        # If empty input, continue
        if not user_question.strip():
            continue
        
        # Get response from OpenAI
        print("\nThinking...")
        response = ask_openai(user_question, model=args.model)
        
        # Print response with nice formatting
        print("\nAI:", response)
        print("-" * 50)

if __name__ == "__main__":
    main()